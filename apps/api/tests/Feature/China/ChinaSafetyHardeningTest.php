<?php

namespace Tests\Feature\China;

use App\Actions\AdminOrders\CancelOrderAction;
use App\Enums\ActivityEventType;
use App\Enums\ChinaProcurementRequirementStatus;
use App\Enums\CommerceChannelCode;
use App\Enums\OrderStatus;
use App\Enums\PaymentTransactionStatus;
use App\Events\Audit\PaymentConfirmed;
use App\Models\ActivityLog;
use App\Models\Admin;
use App\Models\ChinaCommercialStock;
use App\Models\ChinaProcurementRequirement;
use App\Models\ChinaProcurementRequirementLink;
use App\Models\CommerceChannel;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Supplier;
use App\Models\User;
use App\Models\VariantInventory;
use App\Services\China\Procurement\ChinaProcurementBoardEngine;
use App\Services\Payments\Orchestration\DTOs\PaymentProviderResult;
use App\Services\Payments\Orchestration\PaymentTransactionCompletionService;
use App\Support\Admin\AdminPermissions;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ChinaSafetyHardeningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    public function test_duplicate_payment_confirmed_does_not_double_procurement_demand(): void
    {
        [$product, $variant, $channel, $order] = $this->chinaPaidOrderContext(quantity: 2, commercialAvailable: 10);

        event(PaymentConfirmed::fromOrder($order));
        event(PaymentConfirmed::fromOrder($order->fresh()));

        $this->assertSame(1, ChinaProcurementRequirement::query()->count());
        $this->assertSame(1, ChinaProcurementRequirementLink::query()->count());

        $requirement = ChinaProcurementRequirement::query()->firstOrFail();
        $this->assertSame(2, (int) $requirement->quantity_required);

        $stock = ChinaCommercialStock::query()->where('product_variant_id', $variant->id)->firstOrFail();
        $this->assertSame(8, (int) $stock->available_quantity);
        $this->assertSame(2, (int) $stock->reserved_quantity);
        $this->assertSame(2, (int) $stock->ordered_quantity);
    }

    public function test_duplicate_record_paid_order_demand_is_idempotent(): void
    {
        [, $variant, , $order] = $this->chinaPaidOrderContext(quantity: 3, commercialAvailable: 12);

        $engine = app(ChinaProcurementBoardEngine::class);
        $engine->recordPaidOrderDemand($order);
        $engine->recordPaidOrderDemand($order->fresh());

        $this->assertSame(1, ChinaProcurementRequirementLink::query()->count());
        $this->assertSame(3, (int) ChinaProcurementRequirement::query()->value('quantity_required'));

        $stock = ChinaCommercialStock::query()->where('product_variant_id', $variant->id)->firstOrFail();
        $this->assertSame(9, (int) $stock->available_quantity);
        $this->assertSame(3, (int) $stock->reserved_quantity);
    }

    public function test_payment_completion_retry_with_same_transaction_is_idempotent_for_china_procurement(): void
    {
        [, $variant, , $order, $transaction] = $this->chinaPaidOrderContext(
            quantity: 2,
            commercialAvailable: 10,
            pendingPayment: true,
        );

        $service = app(PaymentTransactionCompletionService::class);
        $result = new PaymentProviderResult(
            ok: true,
            status: PaymentTransactionStatus::Successful,
            providerReference: 'NMB-RETRY-1',
            externalTransactionId: 'NMB-EXT-1',
        );

        $service->applyResult($transaction, $result);
        $service->applyResult($transaction->fresh(), $result);

        $this->assertSame(1, ChinaProcurementRequirementLink::query()->where('order_id', $order->id)->count());
        $stock = ChinaCommercialStock::query()->where('product_variant_id', $variant->id)->firstOrFail();
        $this->assertSame(8, (int) $stock->available_quantity);
        $this->assertSame(2, (int) $stock->reserved_quantity);
    }

    public function test_cancel_pending_purchase_reverses_commercial_stock_and_procurement_without_main_mutation(): void
    {
        [, $variant, , $order, , $main] = $this->chinaPaidOrderContext(quantity: 2, commercialAvailable: 10, mainOnHand: 40);

        app(ChinaProcurementBoardEngine::class)->recordPaidOrderDemand($order);

        $admin = Admin::factory()->withPermissions([AdminPermissions::ORDERS_CANCEL])->create();
        Sanctum::actingAs($admin);

        app(CancelOrderAction::class)->handle($order->fresh(), 'Customer changed mind');

        $this->assertDatabaseMissing('china_procurement_requirement_links', ['order_id' => $order->id]);
        $this->assertSame(0, (int) ChinaProcurementRequirement::query()->value('quantity_required'));

        $stock = ChinaCommercialStock::query()->where('product_variant_id', $variant->id)->firstOrFail();
        $this->assertSame(10, (int) $stock->available_quantity);
        $this->assertSame(0, (int) $stock->reserved_quantity);
        $this->assertSame(0, (int) $stock->ordered_quantity);

        $this->assertSame(40, (int) $main->fresh()->on_hand);

        $this->assertTrue(
            ActivityLog::query()
                ->where('event_type', ActivityEventType::ChinaPurchaseRequirementCancelled->value)
                ->where('subject_id', $order->id)
                ->exists(),
        );
    }

    public function test_cancel_after_procurement_started_reverses_demand_but_preserves_purchased_progress(): void
    {
        [, $variant, , $order] = $this->chinaPaidOrderContext(quantity: 4, commercialAvailable: 20);

        $engine = app(ChinaProcurementBoardEngine::class);
        $engine->recordPaidOrderDemand($order);

        $admin = Admin::factory()->withPermissions([
            AdminPermissions::PROCUREMENT_UPDATE,
            AdminPermissions::ORDERS_CANCEL,
        ])->create();

        $requirement = ChinaProcurementRequirement::query()->firstOrFail();
        $engine->markPurchased($admin, $requirement, 2);

        app(CancelOrderAction::class)->handle($order->fresh(), 'Cancel after partial purchase');

        $requirement->refresh();
        $this->assertSame(0, (int) $requirement->quantity_required);
        $this->assertSame(2, (int) $requirement->quantity_purchased);
        $this->assertSame(ChinaProcurementRequirementStatus::Purchasing, $requirement->status);
        $this->assertDatabaseMissing('china_procurement_requirement_links', ['order_id' => $order->id]);

        $stock = ChinaCommercialStock::query()->where('product_variant_id', $variant->id)->firstOrFail();
        $this->assertSame(20, (int) $stock->available_quantity);
        $this->assertSame(0, (int) $stock->reserved_quantity);
    }

    public function test_procurement_reconciliation_reports_healthy_after_payment(): void
    {
        [, , , $order] = $this->chinaPaidOrderContext(quantity: 2, commercialAvailable: 8);
        app(ChinaProcurementBoardEngine::class)->recordPaidOrderDemand($order);

        $admin = Admin::factory()->withPermissions([AdminPermissions::PROCUREMENT_VIEW])->create();
        Sanctum::actingAs($admin);

        $this->getJson('/api/v1/admin/china/procurement/reconciliation')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'healthy')
            ->assertJsonPath('data.summary.critical_count', 0);
    }

    public function test_procurement_reconciliation_detects_missing_requirement_link(): void
    {
        [, , , $order] = $this->chinaPaidOrderContext(quantity: 1, commercialAvailable: 5);

        $admin = Admin::factory()->withPermissions([AdminPermissions::PROCUREMENT_VIEW])->create();
        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/v1/admin/china/procurement/reconciliation')
            ->assertOk()
            ->assertJsonPath('data.status', 'critical');

        $checks = collect($response->json('data.checks'));
        $missing = $checks->firstWhere('group', 'missing_requirement_links');
        $this->assertNotNull($missing);
        $this->assertSame('critical', $missing['status']);
    }

    public function test_procurement_reconciliation_requires_permission(): void
    {
        Sanctum::actingAs(Admin::factory()->withoutPermissions()->create());
        $this->getJson('/api/v1/admin/china/procurement/reconciliation')->assertForbidden();
    }

    /**
     * @return array{0: Product, 1: ProductVariant, 2: CommerceChannel, 3: Order, 4?: \App\Models\PaymentTransaction, 5?: VariantInventory}
     */
    private function chinaPaidOrderContext(
        int $quantity,
        int $commercialAvailable,
        int $mainOnHand = 0,
        bool $pendingPayment = false,
    ): array {
        $channel = CommerceChannel::query()->where('code', CommerceChannelCode::ChinaImport->value)->firstOrFail();
        $supplier = Supplier::factory()->create(['is_active' => true]);
        $product = Product::factory()->fromChina()->create([
            'commerce_channel_id' => $channel->id,
            'supplier_id' => $supplier->id,
        ]);
        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'is_active' => true,
        ]);

        ChinaCommercialStock::query()->create([
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'available_quantity' => $commercialAvailable,
            'reserved_quantity' => 0,
            'ordered_quantity' => 0,
        ]);

        $main = VariantInventory::factory()->create([
            'product_variant_id' => $variant->id,
            'warehouse_code' => 'MAIN',
            'on_hand' => $mainOnHand,
            'reserved' => 0,
            'is_active' => true,
        ]);

        $user = User::factory()->create();
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'status' => $pendingPayment ? OrderStatus::PendingPayment : OrderStatus::Paid,
            'paid_at' => $pendingPayment ? null : now(),
            'commerce_channel_id' => $channel->id,
            'commerce_channel_snapshot' => [
                'id' => $channel->id,
                'code' => CommerceChannelCode::ChinaImport->value,
                'name' => $channel->name,
            ],
            'subtotal' => 10000 * $quantity,
            'total' => 10000 * $quantity,
            'currency' => 'TZS',
        ]);

        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'quantity' => $quantity,
            'unit_price' => 10000,
            'line_total' => 10000 * $quantity,
            'variant_name_snapshot' => $variant->name,
        ]);

        $order = $order->fresh(['items.product.commerceChannel', 'items.variant']);

        if (! $pendingPayment) {
            return [$product, $variant, $channel, $order, null, $main];
        }

        $transaction = \App\Models\PaymentTransaction::factory()->create([
            'order_id' => $order->id,
            'status' => PaymentTransactionStatus::Pending,
            'amount' => $order->total,
            'currency' => $order->currency,
        ]);

        return [$product, $variant, $channel, $order, $transaction, $main];
    }
}
