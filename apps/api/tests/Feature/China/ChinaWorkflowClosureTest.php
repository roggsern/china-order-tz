<?php

namespace Tests\Feature\China;

use App\Enums\ChinaQcStatus;
use App\Enums\ChinaWorkflowStage;
use App\Enums\CommerceChannelCode;
use App\Enums\DeliveryShippingMethod;
use App\Enums\DeliveryType;
use App\Enums\FulfillmentStatus;
use App\Enums\FulfillmentStrategy;
use App\Enums\OrderStatus;
use App\Enums\PaymentTransactionStatus;
use App\Enums\PurchaseOrderStatus;
use App\Enums\SupplierPoResponse;
use App\Enums\WarehouseJobStatus;
use App\Models\Admin;
use App\Models\CommerceChannel;
use App\Models\DeliveryAddress;
use App\Models\DeliveryOption;
use App\Models\Fulfillment;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ProductShippingOption;
use App\Models\PurchaseOrder;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use App\Models\User;
use App\Models\WarehouseJob;
use App\Services\China\ChinaWorkflowEngine;
use App\Services\Fulfillment\FulfillmentEngine;
use App\Services\Payments\Orchestration\DTOs\PaymentProviderResult;
use App\Services\Payments\Orchestration\PaymentTransactionCompletionService;
use App\Services\Shipments\ShipmentEligibilityService;
use App\Services\Warehouse\WarehouseEngine;
use Database\Factories\Support\CatalogCartFixture;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Launch Closure #3 — China Workflow official production path.
 */
class ChinaWorkflowClosureTest extends TestCase
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
                    'id' => 'SESSION-CN-1',
                    'successIndicator' => 'indicator-cn',
                    'checkoutUrl' => 'https://checkout.nmb.test/pay/cn',
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

    private function createPaidChinaOrder(DeliveryType $delivery = DeliveryType::CompanyShipping): array
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
        $payload = $delivery === DeliveryType::CustomerAgent
            ? [
                'shipping_choice' => 'customer_agent',
                'agent_name' => 'Agent Asha',
                'agent_contact' => '+255700000001',
            ]
            : [
                'shipping_choice' => 'company_shipping',
                'shipping_method' => 'air',
            ];

        ['order_id' => $orderId] = $this->createOrderWithShippingChoice($payload);

        $order = Order::query()->findOrFail($orderId);
        // Ensure channel snapshot is China even if cart inference differs.
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

        return compact('user', 'order', 'supplier', 'variant', 'product');
    }

    public function test_paid_china_order_bootstraps_workflow_and_purchase_orders(): void
    {
        ['order' => $order, 'supplier' => $supplier] = $this->createPaidChinaOrder();

        $this->assertSame(OrderStatus::Paid, $order->status);
        $this->assertNotNull($order->fulfillment);
        $this->assertSame(FulfillmentStrategy::China, $order->fulfillment->strategy);

        $workflow = app(ChinaWorkflowEngine::class)->showForOrder($order);
        $this->assertNotNull($workflow);
        $this->assertSame(ChinaWorkflowStage::ProcurementInProgress, $workflow->stage);

        $this->assertDatabaseHas('purchase_orders', [
            'order_id' => $order->id,
            'supplier_id' => $supplier->id,
            'status' => PurchaseOrderStatus::Draft->value,
        ]);

        $this->assertSame(
            1,
            PurchaseOrder::query()->where('order_id', $order->id)->count(),
        );

        // Duplicate bootstrap is idempotent.
        app(ChinaWorkflowEngine::class)->bootstrapFromFulfillment($order->fulfillment);
        $this->assertSame(1, PurchaseOrder::query()->where('order_id', $order->id)->count());
    }

    public function test_supplier_accept_reject_and_reassignment_history(): void
    {
        ['order' => $order, 'variant' => $variant] = $this->createPaidChinaOrder();
        $admin = Admin::factory()->superAdmin()->create();
        Sanctum::actingAs($admin);

        $po = PurchaseOrder::query()->where('order_id', $order->id)->firstOrFail();

        $this->postJson("/api/v1/admin/purchase-orders/{$po->id}/supplier-response", [
            'response' => SupplierPoResponse::Accepted->value,
            'notes' => 'Accepted by supplier A',
        ])->assertOk()
            ->assertJsonPath('data.status', PurchaseOrderStatus::Confirmed->value);

        // Reject path on a second supplier PO.
        $supplierB = $this->mapSupplier($variant, Supplier::factory()->create(['is_active' => true]));
        $poB = app(\App\Services\Procurement\PurchaseOrderEngine::class)->create([
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
            'response' => SupplierPoResponse::Rejected->value,
            'notes' => 'Out of stock',
        ])->assertOk()
            ->assertJsonPath('data.status', PurchaseOrderStatus::Cancelled->value);

        $this->assertDatabaseHas('china_workflow_histories', [
            'order_id' => $order->id,
            'action' => 'supplier_response',
        ]);
    }

    public function test_receiving_qc_consolidation_export_and_company_shipping_gate(): void
    {
        ['order' => $order] = $this->createPaidChinaOrder(DeliveryType::CompanyShipping);
        $admin = Admin::factory()->superAdmin()->create();
        Sanctum::actingAs($admin);

        $po = PurchaseOrder::query()->where('order_id', $order->id)->firstOrFail();
        $this->postJson("/api/v1/admin/purchase-orders/{$po->id}/supplier-response", [
            'response' => SupplierPoResponse::Accepted->value,
        ])->assertOk();

        $itemId = $po->fresh()->items()->first()->id;
        $this->postJson("/api/v1/admin/purchase-orders/{$po->id}/receive", [
            'items' => [['purchase_order_item_id' => $itemId, 'quantity' => 1]],
        ])->assertCreated();

        $workflow = app(ChinaWorkflowEngine::class)->showForOrder($order->fresh());
        $this->assertSame(ChinaWorkflowStage::QcPending, $workflow->stage);

        // QC fail blocks export.
        $this->postJson("/api/v1/admin/orders/{$order->id}/china-workflow/qc", [
            'status' => ChinaQcStatus::Failed->value,
            'notes' => 'Damaged carton',
        ])->assertOk();

        $this->postJson("/api/v1/admin/orders/{$order->id}/china-workflow/export-ready", [
            'commercial_invoice' => true,
            'packing_list' => true,
            'customs_docs' => true,
            'weight_confirmed' => true,
            'dimensions_confirmed' => true,
        ])->assertStatus(422);

        // Reinspection + pass.
        $this->postJson("/api/v1/admin/orders/{$order->id}/china-workflow/qc", [
            'status' => ChinaQcStatus::Passed->value,
            'notes' => 'Replaced and passed',
            'idempotency_key' => 'qc-pass-1',
        ])->assertOk();

        // Packing must complete before Export Ready (Warehouse owns packing; engine consumes it).
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

        $this->postJson("/api/v1/admin/orders/{$order->id}/china-workflow/export-ready", [
            'commercial_invoice' => true,
            'packing_list' => true,
            'customs_docs' => true,
            'weight_confirmed' => true,
            'dimensions_confirmed' => true,
        ])->assertOk();

        // Duplicate export is idempotent.
        $this->postJson("/api/v1/admin/orders/{$order->id}/china-workflow/export-ready", [
            'commercial_invoice' => true,
            'packing_list' => true,
            'customs_docs' => true,
            'weight_confirmed' => true,
            'dimensions_confirmed' => true,
        ])->assertOk();

        app(FulfillmentEngine::class)->updateStatus($fulfillment->fresh(), [
            'status' => FulfillmentStatus::ReadyForShipping->value,
        ]);

        $eligibility = app(ShipmentEligibilityService::class)->evaluate($fulfillment->fresh(['order.deliveryOption', 'warehouseJob', 'shipment']));
        $this->assertTrue($eligibility['eligible']);
    }

    public function test_customer_agent_handoff_path(): void
    {
        ['order' => $order] = $this->createPaidChinaOrder(DeliveryType::CustomerAgent);
        $admin = Admin::factory()->superAdmin()->create();
        Sanctum::actingAs($admin);

        $po = PurchaseOrder::query()->where('order_id', $order->id)->firstOrFail();
        $this->postJson("/api/v1/admin/purchase-orders/{$po->id}/supplier-response", [
            'response' => SupplierPoResponse::Accepted->value,
        ])->assertOk();
        $itemId = $po->fresh()->items()->first()->id;
        $this->postJson("/api/v1/admin/purchase-orders/{$po->id}/receive", [
            'items' => [['purchase_order_item_id' => $itemId, 'quantity' => 1]],
        ])->assertCreated();

        $this->postJson("/api/v1/admin/orders/{$order->id}/china-workflow/qc", [
            'status' => ChinaQcStatus::Passed->value,
        ])->assertOk();

        $fulfillment = $order->fresh()->fulfillment;
        app(FulfillmentEngine::class)->updateStatus($fulfillment, ['status' => FulfillmentStatus::Processing->value]);
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

        $this->postJson("/api/v1/admin/orders/{$order->id}/china-workflow/export-ready", [
            'commercial_invoice' => true,
            'packing_list' => true,
            'customs_docs' => true,
            'weight_confirmed' => true,
            'dimensions_confirmed' => true,
        ])->assertOk();

        $this->postJson("/api/v1/admin/orders/{$order->id}/china-workflow/agent-handoff", [
            'agent_name' => 'Agent Asha',
            'agent_contact' => '+255700000001',
            'evidence' => 'Signed pickup sheet #1',
        ])->assertOk()
            ->assertJsonPath('data.stage', ChinaWorkflowStage::AgentHandedOff->value);

        // Company shipment still ineligible for Customer Agent delivery.
        app(FulfillmentEngine::class)->updateStatus($fulfillment->fresh(), [
            'status' => FulfillmentStatus::ReadyForShipping->value,
        ]);

        $eligibility = app(ShipmentEligibilityService::class)->evaluate($fulfillment->fresh(['order.deliveryOption', 'warehouseJob']));
        $this->assertFalse($eligibility['eligible']);
        $this->assertSame('Customer Agent', $eligibility['reason']);
    }

    public function test_qc_officer_permission_enforced(): void
    {
        ['order' => $order] = $this->createPaidChinaOrder();
        $role = Role::query()->where('slug', 'store_cashier')->firstOrFail();
        $cashier = Admin::factory()->create([
            'role_id' => $role->id,
            'is_super_admin' => false,
        ]);
        Sanctum::actingAs($cashier);

        $this->postJson("/api/v1/admin/orders/{$order->id}/china-workflow/qc", [
            'status' => ChinaQcStatus::Passed->value,
        ])->assertForbidden();
    }

    public function test_duplicate_receiving_does_not_over_receive(): void
    {
        ['order' => $order] = $this->createPaidChinaOrder();
        $admin = Admin::factory()->superAdmin()->create();
        Sanctum::actingAs($admin);

        $po = PurchaseOrder::query()->where('order_id', $order->id)->firstOrFail();
        $this->postJson("/api/v1/admin/purchase-orders/{$po->id}/supplier-response", [
            'response' => SupplierPoResponse::Accepted->value,
        ])->assertOk();
        $itemId = $po->fresh()->items()->first()->id;

        $this->postJson("/api/v1/admin/purchase-orders/{$po->id}/receive", [
            'items' => [['purchase_order_item_id' => $itemId, 'quantity' => 1]],
        ])->assertCreated();

        $this->postJson("/api/v1/admin/purchase-orders/{$po->id}/receive", [
            'items' => [['purchase_order_item_id' => $itemId, 'quantity' => 1]],
        ])->assertStatus(422);
    }

    public function test_lifecycle_status_not_written_by_china_workflow(): void
    {
        ['order' => $order] = $this->createPaidChinaOrder();
        $this->assertSame(OrderStatus::Paid, $order->fresh()->status);

        $admin = Admin::factory()->superAdmin()->create();
        Sanctum::actingAs($admin);
        $po = PurchaseOrder::query()->where('order_id', $order->id)->firstOrFail();
        $this->postJson("/api/v1/admin/purchase-orders/{$po->id}/supplier-response", [
            'response' => SupplierPoResponse::Accepted->value,
        ])->assertOk();
        $itemId = $po->fresh()->items()->first()->id;
        $this->postJson("/api/v1/admin/purchase-orders/{$po->id}/receive", [
            'items' => [['purchase_order_item_id' => $itemId, 'quantity' => 1]],
        ])->assertCreated();
        $this->postJson("/api/v1/admin/orders/{$order->id}/china-workflow/qc", [
            'status' => ChinaQcStatus::Passed->value,
        ])->assertOk();

        // Top-level lifecycle remains Paid until FulfillmentEngine syncs (not China engine).
        $this->assertSame(OrderStatus::Paid, $order->fresh()->status);
    }
}
