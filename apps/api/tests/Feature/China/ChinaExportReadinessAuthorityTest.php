<?php

namespace Tests\Feature\China;

use App\Enums\ChinaExportReadiness;
use App\Enums\ChinaQcStatus;
use App\Enums\ChinaWorkflowStage;
use App\Enums\CommerceChannelCode;
use App\Enums\DeliveryType;
use App\Enums\FulfillmentStatus;
use App\Enums\FulfillmentStrategy;
use App\Enums\PaymentTransactionStatus;
use App\Enums\PurchaseOrderStatus;
use App\Enums\SupplierPoResponse;
use App\Enums\WarehouseJobStatus;
use App\Models\Admin;
use App\Models\ChinaWorkflowRecord;
use App\Models\CommerceChannel;
use App\Models\DeliveryAddress;
use App\Models\Fulfillment;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductShippingOption;
use App\Models\ProductVariant;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use App\Models\User;
use App\Models\WarehouseJob;
use App\Services\China\ChinaWorkflowEngine;
use App\Services\Fulfillment\FulfillmentEngine;
use App\Services\Payments\Orchestration\DTOs\PaymentProviderResult;
use App\Services\Payments\Orchestration\PaymentTransactionCompletionService;
use App\Services\Procurement\PurchaseOrderEngine;
use App\Services\Shipments\ShipmentEligibilityService;
use App\Services\Warehouse\WarehouseEngine;
use Database\Factories\Support\CatalogCartFixture;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;
use Mockery;
use ReflectionClass;
use Tests\TestCase;

/**
 * Export Readiness authority — ChinaWorkflowEngine is the sole owner;
 * ShipmentEligibilityService only consumes the authoritative flag.
 */
class ChinaExportReadinessAuthorityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);

        config([
            'services.nmb.enabled' => true,
            'services.nmb.base_url' => 'https://sandbox.nmb.test',
            'services.nmb.api_version' => '85',
            'services.nmb.merchant_id' => 'TESTMERCHANT',
            'services.nmb.username' => 'merchant.TESTMERCHANT',
            'services.nmb.password' => 'sandbox-password',
            'services.nmb.return_url' => 'https://app.chinaorder.test/payments/return',
            'services.nmb.callback_url' => 'https://api.chinaorder.test/api/v1/payments/nmb/callback',
            'services.nmb.merchant_name' => 'China Order TZ',
            'services.nmb.merchant_url' => 'https://chinaorder.test',
            'services.nmb.webhook.require_signature' => false,
            'payments.nmb.base_url' => 'https://sandbox.nmb.test',
            'payments.nmb.merchant_id' => 'TESTMERCHANT',
            'payments.nmb.password' => 'sandbox-password',
            'payments.orchestrator.default_provider' => 'nmb',
        ]);

        Http::fake([
            'sandbox.nmb.test/*/session' => Http::response([
                'result' => 'SUCCESS',
                'session' => [
                    'id' => 'SESSION-CN-ER',
                    'successIndicator' => 'indicator-er',
                    'checkoutUrl' => 'https://checkout.nmb.test/pay/er',
                ],
            ]),
            'sandbox.nmb.test/*/order/*' => Http::response([
                'result' => 'PENDING',
                'order' => ['id' => 'pending', 'amount' => '0.00', 'currency' => 'TZS'],
            ]),
        ]);

        CommerceChannel::query()->updateOrCreate(
            ['code' => CommerceChannelCode::ChinaImport->value],
            ['name' => 'Order From China', 'description' => 'Import', 'is_active' => true],
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function mapSupplier(ProductVariant $variant, ?Supplier $supplier = null): Supplier
    {
        $supplier ??= Supplier::factory()->create(['is_active' => true, 'country' => 'CN']);
        SupplierProduct::query()->updateOrCreate(
            [
                'supplier_id' => $supplier->id,
                'product_variant_id' => $variant->id,
            ],
            [
                'supplier_sku' => 'CN-'.$variant->sku,
                'purchase_cost' => 10000,
                'currency' => 'TZS',
                'lead_time_days' => 7,
                'is_active' => true,
            ],
        );

        return $supplier;
    }

    /** @return array{order: Order, admin: Admin, variant: ProductVariant, supplier: Supplier} */
    private function paidChinaOrderReadyForReceive(): array
    {
        $user = User::factory()->create();
        DeliveryAddress::factory()->create(['user_id' => $user->id]);
        ['product' => $product, 'variant' => $variant] = CatalogCartFixture::purchasable(30000);

        $china = CommerceChannel::query()->where('code', CommerceChannelCode::ChinaImport->value)->firstOrFail();
        Product::query()->whereKey($product->id)->update([
            'commerce_channel_id' => $china->id,
            'fulfillment_source' => CommerceChannelCode::ChinaImport->fulfillmentSource(),
            'air_shipping_price' => 8000,
        ]);
        ProductShippingOption::query()->where('product_id', $product->id)->forceDelete();
        ProductShippingOption::factory()->air(8000)->create(['product_id' => $product->id]);

        $supplier = $this->mapSupplier($variant);

        $cart = \App\Models\Cart::factory()->create([
            'user_id' => $user->id,
            'status' => \App\Enums\CartStatus::Active,
            'currency' => 'TZS',
        ]);
        \App\Models\CartItem::factory()->create([
            'cart_id' => $cart->id,
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'quantity' => 1,
            'unit_price' => 30000,
            'price_snapshot' => 30000,
            'currency' => 'TZS',
        ]);

        Sanctum::actingAs($user);
        ['order_id' => $orderId] = $this->createOrderWithShippingChoice([
            'shipping_choice' => 'company_shipping',
            'shipping_method' => 'air',
        ]);

        $order = Order::query()->findOrFail($orderId);
        $order->forceFill([
            'commerce_channel_id' => $china->id,
            'commerce_channel_snapshot' => [
                'id' => $china->id,
                'code' => CommerceChannelCode::ChinaImport->value,
                'name' => $china->name,
            ],
        ])->save();

        $transactionId = $this->postJson("/api/v1/payments/start/{$order->id}")->json('data.id');
        $transaction = \App\Models\PaymentTransaction::query()->findOrFail($transactionId);
        app(PaymentTransactionCompletionService::class)->applyResult(
            $transaction,
            new PaymentProviderResult(
                ok: true,
                status: PaymentTransactionStatus::Successful,
                providerReference: $transaction->provider_reference,
            ),
        );

        $order = $order->fresh(['fulfillment', 'items', 'deliveryOption']);
        $admin = Admin::factory()->superAdmin()->create();
        Sanctum::actingAs($admin);

        return compact('order', 'admin', 'variant', 'supplier');
    }

    private function acceptAndReceive(Order $order): void
    {
        $po = PurchaseOrder::query()->where('order_id', $order->id)->firstOrFail();
        $this->postJson("/api/v1/admin/purchase-orders/{$po->id}/supplier-response", [
            'response' => SupplierPoResponse::Accepted->value,
        ])->assertOk();
        $itemId = $po->fresh()->items()->first()->id;
        $this->postJson("/api/v1/admin/purchase-orders/{$po->id}/receive", [
            'items' => [['purchase_order_item_id' => $itemId, 'quantity' => 1]],
        ])->assertCreated();
    }

    private function advancePackingToReadyToShip(Order $order): void
    {
        $fulfillment = $order->fresh()->fulfillment;
        app(FulfillmentEngine::class)->updateStatus($fulfillment, [
            'status' => FulfillmentStatus::Processing->value,
        ]);
        $job = WarehouseJob::query()->where('fulfillment_id', $fulfillment->id)->firstOrFail();
        $wh = app(WarehouseEngine::class);
        foreach ([
            WarehouseJobStatus::Picking,
            WarehouseJobStatus::Picked,
            WarehouseJobStatus::Packing,
            WarehouseJobStatus::Packed,
            WarehouseJobStatus::ReadyToShip,
        ] as $status) {
            $wh->updateStatus($job->fresh(), ['status' => $status->value]);
        }
    }

    private function fullChecklist(bool $omit = false): array
    {
        return [
            'commercial_invoice' => true,
            'packing_list' => ! $omit,
            'customs_docs' => true,
            'weight_confirmed' => true,
            'dimensions_confirmed' => true,
        ];
    }

    public function test_qc_fail_keeps_export_ready_false(): void
    {
        ['order' => $order] = $this->paidChinaOrderReadyForReceive();
        $this->acceptAndReceive($order);

        $this->postJson("/api/v1/admin/orders/{$order->id}/china-workflow/qc", [
            'status' => ChinaQcStatus::Failed->value,
            'notes' => 'Damaged',
        ])->assertOk();

        $this->advancePackingToReadyToShip($order);

        $engine = app(ChinaWorkflowEngine::class);
        $record = $engine->showForOrder($order->fresh());
        $this->assertSame(ChinaExportReadiness::NotReady, $record->exportReadiness());

        $this->expectException(ValidationException::class);
        $engine->markExportReady($order->fresh(), Admin::factory()->superAdmin()->create(), $this->fullChecklist());
    }

    public function test_qc_pass_without_packing_keeps_export_ready_false(): void
    {
        ['order' => $order, 'admin' => $admin] = $this->paidChinaOrderReadyForReceive();
        $this->acceptAndReceive($order);

        $this->postJson("/api/v1/admin/orders/{$order->id}/china-workflow/qc", [
            'status' => ChinaQcStatus::Passed->value,
        ])->assertOk();

        // No packing yet.
        $this->postJson("/api/v1/admin/orders/{$order->id}/china-workflow/export-ready", $this->fullChecklist())
            ->assertStatus(422);

        $record = app(ChinaWorkflowEngine::class)->showForOrder($order->fresh());
        $this->assertFalse($record->isAuthoritativelyExportReady());
        $this->assertSame(ChinaExportReadiness::NotReady, $record->exportReadiness());
        $this->assertNull($record->export_ready_at);
    }

    public function test_multi_supplier_incomplete_consolidation_blocks_export_ready(): void
    {
        ['order' => $order, 'admin' => $admin, 'variant' => $variant] = $this->paidChinaOrderReadyForReceive();

        $poA = PurchaseOrder::query()->where('order_id', $order->id)->firstOrFail();
        $this->postJson("/api/v1/admin/purchase-orders/{$poA->id}/supplier-response", [
            'response' => SupplierPoResponse::Accepted->value,
        ])->assertOk();
        $itemA = $poA->fresh()->items()->first()->id;
        $this->postJson("/api/v1/admin/purchase-orders/{$poA->id}/receive", [
            'items' => [['purchase_order_item_id' => $itemA, 'quantity' => 1]],
        ])->assertCreated();

        $supplierB = $this->mapSupplier($variant, Supplier::factory()->create(['is_active' => true, 'country' => 'CN']));
        $poB = app(PurchaseOrderEngine::class)->create([
            'supplier_id' => $supplierB->id,
            'order_id' => $order->id,
            'fulfillment_id' => $order->fulfillment_id,
            'idempotency_key' => 'china-po:'.$order->id.':'.$supplierB->id,
            'items' => [[
                'product_variant_id' => $variant->id,
                'quantity_ordered' => 1,
                'unit_cost' => 9000,
            ]],
        ], $admin);
        $this->postJson("/api/v1/admin/purchase-orders/{$poB->id}/supplier-response", [
            'response' => SupplierPoResponse::Accepted->value,
        ])->assertOk();
        $itemB = $poB->fresh()->items()->first()->id;
        $this->postJson("/api/v1/admin/purchase-orders/{$poB->id}/receive", [
            'items' => [['purchase_order_item_id' => $itemB, 'quantity' => 1]],
        ])->assertCreated();

        $this->assertGreaterThan(
            1,
            PurchaseOrder::query()
                ->where('order_id', $order->id)
                ->where('status', '!=', PurchaseOrderStatus::Cancelled->value)
                ->count(),
        );

        $this->postJson("/api/v1/admin/orders/{$order->id}/china-workflow/qc", [
            'status' => ChinaQcStatus::Passed->value,
        ])->assertOk();
        $this->advancePackingToReadyToShip($order);

        // Consolidation not completed → Export Ready must stay FALSE.
        $this->postJson("/api/v1/admin/orders/{$order->id}/china-workflow/export-ready", $this->fullChecklist())
            ->assertStatus(422);

        $record = app(ChinaWorkflowEngine::class)->showForOrder($order->fresh());
        $this->assertFalse($record->isAuthoritativelyExportReady());

        $this->postJson("/api/v1/admin/orders/{$order->id}/china-workflow/consolidate", [
            'batch' => 'BATCH-MULTI-1',
        ])->assertOk();

        $this->postJson("/api/v1/admin/orders/{$order->id}/china-workflow/export-ready", $this->fullChecklist())
            ->assertOk();

        $record = app(ChinaWorkflowEngine::class)->showForOrder($order->fresh());
        $this->assertTrue($record->isAuthoritativelyExportReady());
        $this->assertSame(ChinaExportReadiness::ExportReady, $record->exportReadiness());
    }

    public function test_incomplete_checklist_keeps_export_ready_false(): void
    {
        ['order' => $order] = $this->paidChinaOrderReadyForReceive();
        $this->acceptAndReceive($order);
        $this->postJson("/api/v1/admin/orders/{$order->id}/china-workflow/qc", [
            'status' => ChinaQcStatus::Passed->value,
        ])->assertOk();
        $this->advancePackingToReadyToShip($order);

        $this->postJson("/api/v1/admin/orders/{$order->id}/china-workflow/export-ready", $this->fullChecklist(omit: true))
            ->assertStatus(422);

        $this->assertFalse(
            app(ChinaWorkflowEngine::class)->showForOrder($order->fresh())->isAuthoritativelyExportReady()
        );
    }

    public function test_packing_incomplete_keeps_export_ready_false(): void
    {
        ['order' => $order] = $this->paidChinaOrderReadyForReceive();
        $this->acceptAndReceive($order);
        $this->postJson("/api/v1/admin/orders/{$order->id}/china-workflow/qc", [
            'status' => ChinaQcStatus::Passed->value,
        ])->assertOk();

        // Start packing but stop at Packing (not Packed / ReadyToShip).
        $fulfillment = $order->fresh()->fulfillment;
        app(FulfillmentEngine::class)->updateStatus($fulfillment, [
            'status' => FulfillmentStatus::Processing->value,
        ]);
        $job = WarehouseJob::query()->where('fulfillment_id', $fulfillment->id)->firstOrFail();
        $wh = app(WarehouseEngine::class);
        foreach ([
            WarehouseJobStatus::Picking,
            WarehouseJobStatus::Picked,
            WarehouseJobStatus::Packing,
        ] as $status) {
            $wh->updateStatus($job->fresh(), ['status' => $status->value]);
        }

        $this->postJson("/api/v1/admin/orders/{$order->id}/china-workflow/export-ready", $this->fullChecklist())
            ->assertStatus(422);

        $this->assertFalse(
            app(ChinaWorkflowEngine::class)->showForOrder($order->fresh())->isAuthoritativelyExportReady()
        );
    }

    public function test_everything_complete_sets_export_ready_true_with_audit(): void
    {
        ['order' => $order, 'admin' => $admin] = $this->paidChinaOrderReadyForReceive();
        $this->acceptAndReceive($order);
        $this->postJson("/api/v1/admin/orders/{$order->id}/china-workflow/qc", [
            'status' => ChinaQcStatus::Passed->value,
        ])->assertOk();
        $this->advancePackingToReadyToShip($order);

        $this->postJson("/api/v1/admin/orders/{$order->id}/china-workflow/export-ready", array_merge(
            $this->fullChecklist(),
            ['notes' => 'All docs verified'],
        ))->assertOk();

        $record = app(ChinaWorkflowEngine::class)->showForOrder($order->fresh());
        $this->assertTrue($record->isAuthoritativelyExportReady());
        $this->assertNotNull($record->export_ready_at);
        $this->assertSame(ChinaExportReadiness::ExportReady, $record->exportReadiness());
        $this->assertTrue(in_array($record->stage, [
            ChinaWorkflowStage::ExportReady,
            ChinaWorkflowStage::CompanyShippingReady,
        ], true));

        $this->assertDatabaseHas('china_workflow_histories', [
            'order_id' => $order->id,
            'action' => 'export_ready',
            'admin_id' => $admin->id,
            'idempotency_key' => 'china-export-ready:'.$order->id,
        ]);

        // History is append-only / not overwritten on idempotent retry.
        $historyCount = \App\Models\ChinaWorkflowHistory::query()
            ->where('order_id', $order->id)
            ->where('action', 'export_ready')
            ->count();
        $this->postJson("/api/v1/admin/orders/{$order->id}/china-workflow/export-ready", $this->fullChecklist())
            ->assertOk();
        $this->assertSame(
            $historyCount,
            \App\Models\ChinaWorkflowHistory::query()
                ->where('order_id', $order->id)
                ->where('action', 'export_ready')
                ->count(),
        );
    }

    public function test_shipment_eligibility_only_reads_export_ready_flag(): void
    {
        ['order' => $order] = $this->paidChinaOrderReadyForReceive();
        $this->acceptAndReceive($order);
        $this->postJson("/api/v1/admin/orders/{$order->id}/china-workflow/qc", [
            'status' => ChinaQcStatus::Passed->value,
        ])->assertOk();
        $this->advancePackingToReadyToShip($order);

        $fulfillment = $order->fresh()->fulfillment;
        app(FulfillmentEngine::class)->updateStatus($fulfillment->fresh(), [
            'status' => FulfillmentStatus::ReadyForShipping->value,
        ]);

        // Without Export Ready → blocked by consumer message only.
        $blocked = app(ShipmentEligibilityService::class)->evaluate(
            $fulfillment->fresh(['order.deliveryOption', 'warehouseJob', 'shipment'])
        );
        $this->assertFalse($blocked['eligible']);
        $this->assertSame('China export readiness is required before company shipment.', $blocked['reason']);

        // Stamp authoritative flag only (simulates prior markExportReady). Eligibility must not re-check QC.
        ChinaWorkflowRecord::query()->where('order_id', $order->id)->update([
            'qc_status' => ChinaQcStatus::Failed->value,
            'stage' => ChinaWorkflowStage::QcFailed->value,
            'export_ready_at' => now(),
        ]);

        $allowed = app(ShipmentEligibilityService::class)->evaluate(
            $fulfillment->fresh(['order.deliveryOption', 'warehouseJob', 'shipment'])
        );
        $this->assertTrue($allowed['eligible']);
    }

    public function test_shipment_eligibility_never_calls_export_evaluation(): void
    {
        ['order' => $order] = $this->paidChinaOrderReadyForReceive();
        $this->advancePackingToReadyToShip($order);
        $fulfillment = $order->fresh()->fulfillment;
        app(FulfillmentEngine::class)->updateStatus($fulfillment->fresh(), [
            'status' => FulfillmentStatus::ReadyForShipping->value,
        ]);

        $mock = Mockery::mock(ChinaWorkflowEngine::class);
        $mock->shouldReceive('isExportReadyForShipment')
            ->once()
            ->with(Mockery::type(Fulfillment::class))
            ->andReturn(true);
        $mock->shouldNotReceive('evaluateExportReadinessBlockers');
        $mock->shouldNotReceive('markExportReady');
        $this->app->instance(ChinaWorkflowEngine::class, $mock);

        $eligibility = $this->app->make(ShipmentEligibilityService::class)->evaluate(
            $fulfillment->fresh(['order.deliveryOption', 'warehouseJob', 'shipment'])
        );
        $this->assertTrue($eligibility['eligible']);

        // Source-level guarantee: eligibility never imports/recalculates export prerequisites.
        $source = file_get_contents((new ReflectionClass(ShipmentEligibilityService::class))->getFileName());
        $this->assertStringNotContainsString('evaluateExportReadinessBlockers', $source);
        $this->assertStringNotContainsString('ChinaQcStatus', $source);
        $this->assertStringNotContainsString('completeConsolidation', $source);
        $this->assertStringNotContainsString('markExportReady', $source);
        $this->assertStringContainsString('isExportReadyForShipment', $source);
    }

    public function test_legacy_china_fulfillment_without_workflow_record_remains_eligible(): void
    {
        ['order' => $order] = $this->paidChinaOrderReadyForReceive();

        // Remove workflow record → legacy path (no Export Ready gate).
        ChinaWorkflowRecord::query()->where('order_id', $order->id)->delete();
        $this->assertNull(app(ChinaWorkflowEngine::class)->showForOrder($order->fresh()));

        $this->advancePackingToReadyToShip($order);
        $fulfillment = $order->fresh()->fulfillment;
        app(FulfillmentEngine::class)->updateStatus($fulfillment->fresh(), [
            'status' => FulfillmentStatus::ReadyForShipping->value,
        ]);

        $this->assertTrue(
            app(ChinaWorkflowEngine::class)->isExportReadyForShipment($fulfillment->fresh())
        );

        $eligibility = app(ShipmentEligibilityService::class)->evaluate(
            $fulfillment->fresh(['order.deliveryOption', 'warehouseJob', 'shipment'])
        );
        $this->assertTrue($eligibility['eligible']);
    }

    public function test_non_china_strategy_skips_export_ready_gate(): void
    {
        $fulfillment = Fulfillment::factory()->create([
            'strategy' => FulfillmentStrategy::Local,
            'status' => FulfillmentStatus::ReadyForShipping,
        ]);

        $this->assertTrue(
            app(ChinaWorkflowEngine::class)->isExportReadyForShipment($fulfillment)
        );
    }
}
