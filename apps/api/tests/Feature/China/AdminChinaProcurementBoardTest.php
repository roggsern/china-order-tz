<?php

namespace Tests\Feature\China;

use App\Enums\ChinaProcurementRequirementStatus;
use App\Enums\ChinaWorkflowStage;
use App\Enums\CommerceChannelCode;
use App\Enums\NotificationEventType;
use App\Enums\OrderStatus;
use App\Enums\ActivityEventType;
use App\Events\Audit\PaymentConfirmed;
use App\Models\ActivityLog;
use App\Models\Admin;
use App\Models\ChinaCommercialStock;
use App\Models\ChinaProcurementRequirement;
use App\Models\ChinaProcurementRequirementLink;
use App\Models\ChinaWorkflowRecord;
use App\Models\CommerceChannel;
use App\Models\Notification;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Supplier;
use App\Models\User;
use App\Services\China\Procurement\ChinaProcurementBoardEngine;
use App\Support\Admin\AdminPermissions;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminChinaProcurementBoardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    public function test_payment_confirmed_aggregates_variant_demand_and_links_orders(): void
    {
        [$product, $variant, $channel] = $this->chinaProductFixtures();

        $orderA = $this->createPaidChinaOrder($channel, [
            ['product' => $product, 'variant' => $variant, 'quantity' => 2],
        ]);
        $orderB = $this->createPaidChinaOrder($channel, [
            ['product' => $product, 'variant' => $variant, 'quantity' => 1],
        ]);

        event(PaymentConfirmed::fromOrder($orderA));
        event(PaymentConfirmed::fromOrder($orderB));

        $requirement = ChinaProcurementRequirement::query()
            ->where('product_id', $product->id)
            ->where('product_variant_id', $variant->id)
            ->first();

        $this->assertNotNull($requirement);
        $this->assertSame(3, (int) $requirement->quantity_required);
        $this->assertSame(ChinaProcurementRequirementStatus::Pending, $requirement->status);
        $this->assertSame(2, ChinaProcurementRequirementLink::query()->where('requirement_id', $requirement->id)->count());
    }

    public function test_commercial_stock_decreases_on_paid_china_import_without_touching_inventory(): void
    {
        [$product, $variant, $channel] = $this->chinaProductFixtures();

        ChinaCommercialStock::query()->create([
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'available_quantity' => 10,
            'reserved_quantity' => 0,
            'ordered_quantity' => 0,
        ]);

        $order = $this->createPaidChinaOrder($channel, [
            ['product' => $product, 'variant' => $variant, 'quantity' => 2],
        ]);

        app(ChinaProcurementBoardEngine::class)->recordPaidOrderDemand($order);

        $stock = ChinaCommercialStock::query()->where('product_variant_id', $variant->id)->first();
        $this->assertSame(8, (int) $stock->available_quantity);
        $this->assertSame(2, (int) $stock->reserved_quantity);
        $this->assertSame(2, (int) $stock->ordered_quantity);
        $this->assertDatabaseMissing('inventory', [
            'product_id' => $product->id,
        ]);
        $this->assertDatabaseMissing('variant_inventories', [
            'product_variant_id' => $variant->id,
        ]);
    }

    public function test_partial_purchase_updates_status_and_linked_workflow(): void
    {
        [$product, $variant, $channel] = $this->chinaProductFixtures();
        $admin = Admin::factory()->withPermissions([
            AdminPermissions::PROCUREMENT_VIEW,
            AdminPermissions::PROCUREMENT_UPDATE,
        ])->create();

        $order = $this->createPaidChinaOrder($channel, [
            ['product' => $product, 'variant' => $variant, 'quantity' => 10],
        ]);

        app(ChinaProcurementBoardEngine::class)->recordPaidOrderDemand($order);

        ChinaWorkflowRecord::query()->create([
            'order_id' => $order->id,
            'stage' => ChinaWorkflowStage::AwaitingProcurement,
        ]);

        $requirement = ChinaProcurementRequirement::query()->firstOrFail();
        $updated = app(ChinaProcurementBoardEngine::class)->markPurchased($admin, $requirement, 7);

        $this->assertSame(7, (int) $updated->quantity_purchased);
        $this->assertSame(3, $updated->remainingQuantity());
        $this->assertSame(ChinaProcurementRequirementStatus::Purchasing, $updated->status);

        $completedPartial = app(ChinaProcurementBoardEngine::class)->markPurchased($admin, $updated, 3);
        $this->assertSame(ChinaProcurementRequirementStatus::Purchased, $completedPartial->status);
        $this->assertSame(ChinaWorkflowStage::ProcurementInProgress, ChinaWorkflowRecord::query()->where('order_id', $order->id)->value('stage'));
    }

    public function test_start_qc_moves_requirement_and_linked_workflow_to_qc_pending(): void
    {
        [$product, $variant, $channel] = $this->chinaProductFixtures();
        $admin = Admin::factory()->withPermissions([AdminPermissions::PROCUREMENT_UPDATE])->create();
        $order = $this->createPaidChinaOrder($channel, [
            ['product' => $product, 'variant' => $variant, 'quantity' => 2],
        ]);

        app(ChinaProcurementBoardEngine::class)->recordPaidOrderDemand($order);
        ChinaWorkflowRecord::query()->create([
            'order_id' => $order->id,
            'stage' => ChinaWorkflowStage::ProcurementInProgress,
        ]);

        $requirement = ChinaProcurementRequirement::query()->firstOrFail();
        app(ChinaProcurementBoardEngine::class)->markPurchased($admin, $requirement, 2);
        $updated = app(ChinaProcurementBoardEngine::class)->startQc($admin, $requirement->fresh());

        $this->assertSame(ChinaProcurementRequirementStatus::QcPending, $updated->status);
        $this->assertSame(ChinaWorkflowStage::QcPending, ChinaWorkflowRecord::query()->where('order_id', $order->id)->value('stage'));
    }

    public function test_complete_moves_requirement_to_completed_and_records_audit_and_notification(): void
    {
        [$product, $variant, $channel] = $this->chinaProductFixtures();
        $admin = Admin::factory()->withPermissions([
            AdminPermissions::PROCUREMENT_VIEW,
            AdminPermissions::PROCUREMENT_UPDATE,
        ])->create(['is_active' => true]);
        $order = $this->createPaidChinaOrder($channel, [
            ['product' => $product, 'variant' => $variant, 'quantity' => 2],
        ]);

        app(ChinaProcurementBoardEngine::class)->recordPaidOrderDemand($order);
        ChinaWorkflowRecord::query()->create([
            'order_id' => $order->id,
            'stage' => ChinaWorkflowStage::ProcurementInProgress,
        ]);

        $requirement = ChinaProcurementRequirement::query()->firstOrFail();
        $engine = app(ChinaProcurementBoardEngine::class);
        $engine->markPurchased($admin, $requirement, 2);
        $engine->startQc($admin, $requirement->fresh());

        Sanctum::actingAs($admin);

        $this->postJson("/api/v1/admin/china/procurement/{$requirement->id}/complete")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', ChinaProcurementRequirementStatus::Completed->value);

        $requirement->refresh();
        $this->assertSame(ChinaProcurementRequirementStatus::Completed, $requirement->status);

        $this->assertTrue(
            ActivityLog::query()
                ->where('event_type', ActivityEventType::ChinaPurchaseCompleted->value)
                ->where('subject_id', $requirement->id)
                ->exists(),
        );

        $this->assertTrue(
            Notification::query()
                ->where('event_type', NotificationEventType::ChinaPurchaseCompleted->value)
                ->where('admin_id', $admin->id)
                ->exists(),
        );
    }

    public function test_admin_api_permissions_and_actions(): void
    {
        [$product, $variant, $channel] = $this->chinaProductFixtures();
        $order = $this->createPaidChinaOrder($channel, [
            ['product' => $product, 'variant' => $variant, 'quantity' => 4],
        ]);
        app(ChinaProcurementBoardEngine::class)->recordPaidOrderDemand($order);
        $requirement = ChinaProcurementRequirement::query()->firstOrFail();

        $this->getJson('/api/v1/admin/china/procurement')->assertUnauthorized();

        Sanctum::actingAs(User::factory()->create());
        $this->getJson('/api/v1/admin/china/procurement')->assertUnauthorized();

        Sanctum::actingAs(Admin::factory()->withoutPermissions()->create());
        $this->getJson('/api/v1/admin/china/procurement')->assertForbidden();

        $admin = Admin::factory()->withPermissions([
            AdminPermissions::PROCUREMENT_VIEW,
            AdminPermissions::PROCUREMENT_UPDATE,
        ])->create();
        Sanctum::actingAs($admin);

        $this->getJson('/api/v1/admin/china/procurement?status=pending')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.quantity_required', 4);

        $this->postJson("/api/v1/admin/china/procurement/{$requirement->id}/mark-purchased", [
            'quantity_purchased' => 2,
        ])
            ->assertOk()
            ->assertJsonPath('data.quantity_purchased', 2)
            ->assertJsonPath('data.status', ChinaProcurementRequirementStatus::Purchasing->value);
    }

    /**
     * @return array{0: Product, 1: ProductVariant, 2: CommerceChannel}
     */
    private function chinaProductFixtures(): array
    {
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

        return [$product, $variant, $channel];
    }

    /**
     * @param  list<array{product: Product, variant: ProductVariant, quantity: int}>  $lines
     */
    private function createPaidChinaOrder(CommerceChannel $channel, array $lines): Order
    {
        $user = User::factory()->create();
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'status' => OrderStatus::Paid,
            'paid_at' => now(),
            'commerce_channel_id' => $channel->id,
            'commerce_channel_snapshot' => [
                'id' => $channel->id,
                'code' => CommerceChannelCode::ChinaImport->value,
                'name' => $channel->name,
            ],
        ]);

        foreach ($lines as $line) {
            OrderItem::factory()->create([
                'order_id' => $order->id,
                'product_id' => $line['product']->id,
                'product_variant_id' => $line['variant']->id,
                'quantity' => $line['quantity'],
                'variant_name_snapshot' => $line['variant']->name,
                'attributes_snapshot' => ['color' => 'Blue', 'size' => 'XL'],
            ]);
        }

        return $order->fresh(['items']);
    }
}
