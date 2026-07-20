<?php

namespace Tests\Feature\CustomerAgent;

use App\Enums\AgentPickupStatus;
use App\Enums\ChinaQcStatus;
use App\Enums\ChinaWorkflowStage;
use App\Enums\CommerceChannelCode;
use App\Enums\DeliveryType;
use App\Enums\FulfillmentStatus;
use App\Enums\FulfillmentStrategy;
use App\Enums\PaymentTransactionStatus;
use App\Enums\PickupAuthorizationStatus;
use App\Enums\SupplierPoResponse;
use App\Enums\WarehouseJobStatus;
use App\Enums\WarehouseReleaseStatus;
use App\Models\Admin;
use App\Models\CommerceChannel;
use App\Models\CustomerAgentPickup;
use App\Models\CustomerAgentPickupHistory;
use App\Models\DeliveryAddress;
use App\Models\Fulfillment;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductShippingOption;
use App\Models\ProductVariant;
use App\Models\PurchaseOrder;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use App\Models\User;
use App\Models\WarehouseJob;
use App\Services\China\ChinaWorkflowEngine;
use App\Services\CustomerAgent\CustomerAgentWorkflowEngine;
use App\Services\Fulfillment\FulfillmentEngine;
use App\Services\Payments\Orchestration\DTOs\PaymentProviderResult;
use App\Services\Payments\Orchestration\PaymentTransactionCompletionService;
use App\Services\Shipments\ShipmentEligibilityService;
use App\Services\Shipments\ShipmentEngine;
use App\Services\Warehouse\WarehouseEngine;
use Database\Factories\Support\CatalogCartFixture;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Launch Closure #4 — Customer Agent Workflow.
 */
class CustomerAgentWorkflowClosureTest extends TestCase
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
                    'id' => 'SESSION-CA-1',
                    'successIndicator' => 'indicator-ca',
                    'checkoutUrl' => 'https://checkout.nmb.test/pay/ca',
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

    /** @return array{order: Order, admin: Admin, user: User} */
    private function createPaidChinaAgentOrder(): array
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
        $this->mapSupplier($variant);

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
            'shipping_choice' => 'customer_agent',
            'agent_name' => 'Agent Asha',
            'agent_contact' => '+255700000001',
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

        $order = $order->fresh(['fulfillment', 'deliveryOption', 'items']);
        $admin = Admin::factory()->superAdmin()->create();
        Sanctum::actingAs($admin);

        return compact('order', 'admin', 'user');
    }

    private function advanceToExportReady(Order $order): void
    {
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

        app(FulfillmentEngine::class)->updateStatus($fulfillment->fresh(), [
            'status' => FulfillmentStatus::ReadyForShipping->value,
        ]);
    }

    public function test_customer_agent_selected_and_company_shipment_blocked(): void
    {
        ['order' => $order] = $this->createPaidChinaAgentOrder();
        $this->assertSame(DeliveryType::CustomerAgent, $order->deliveryOption->delivery_type);
        $this->advanceToExportReady($order);

        $fulfillment = $order->fresh()->fulfillment;
        $eligibility = app(ShipmentEligibilityService::class)->evaluate($fulfillment->fresh([
            'order.deliveryOption', 'warehouseJob', 'shipment',
        ]));
        $this->assertFalse($eligibility['eligible']);
        $this->assertSame('Customer Agent', $eligibility['reason']);

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        app(ShipmentEngine::class)->createForFulfillment($fulfillment->fresh());
    }

    public function test_unauthorized_pickup_rejected(): void
    {
        ['order' => $order] = $this->createPaidChinaAgentOrder();
        $this->advanceToExportReady($order);

        $eval = app(ShipmentEligibilityService::class)->evaluateCustomerAgentPickup(
            $order->fresh()->fulfillment->fresh(['order.deliveryOption', 'warehouseJob']),
            requireAuthorization: true,
        );
        $this->assertFalse($eval['eligible']);

        $this->postJson("/api/v1/admin/orders/{$order->id}/customer-agent/schedule", [
            'scheduled_at' => now()->addDay()->toIso8601String(),
        ])->assertStatus(422);
    }

    public function test_export_not_ready_blocks_authorization(): void
    {
        ['order' => $order] = $this->createPaidChinaAgentOrder();
        // No export ready / warehouse advance.
        $this->postJson("/api/v1/admin/orders/{$order->id}/customer-agent/authorize", [
            'notes' => 'Too early',
        ])->assertStatus(422);
    }

    public function test_warehouse_not_ready_blocks_authorization(): void
    {
        ['order' => $order] = $this->createPaidChinaAgentOrder();
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
        // Skip packing / export.

        $this->postJson("/api/v1/admin/orders/{$order->id}/customer-agent/authorize")
            ->assertStatus(422);
    }

    public function test_authorization_issued_expired_and_revoked(): void
    {
        ['order' => $order] = $this->createPaidChinaAgentOrder();
        $this->advanceToExportReady($order);

        $this->postJson("/api/v1/admin/orders/{$order->id}/customer-agent/authorize", [
            'expires_at' => now()->addDays(3)->toIso8601String(),
            'agent_company' => 'Asha Logistics Ltd',
            'agent_phone' => '+255700000001',
            'agent_email' => 'asha@example.com',
        ])->assertOk()
            ->assertJsonPath('data.authorization_status', PickupAuthorizationStatus::Authorized->value);

        // Duplicate authorize is idempotent.
        $this->postJson("/api/v1/admin/orders/{$order->id}/customer-agent/authorize")
            ->assertOk();
        $this->assertSame(
            1,
            CustomerAgentPickupHistory::query()
                ->where('order_id', $order->id)
                ->where('action', 'authorization_issued')
                ->count(),
        );

        $pickup = CustomerAgentPickup::query()->where('order_id', $order->id)->firstOrFail();
        $pickup->forceFill(['authorization_expires_at' => now()->subMinute()])->save();
        $this->assertFalse($pickup->fresh()->hasValidAuthorization());
        $this->assertSame(PickupAuthorizationStatus::Expired, $pickup->fresh()->authorization_status);

        // Re-authorize then revoke.
        $pickup->forceFill([
            'authorization_status' => PickupAuthorizationStatus::Authorized,
            'authorization_expires_at' => now()->addDay(),
        ])->save();

        $this->postJson("/api/v1/admin/orders/{$order->id}/customer-agent/revoke", [
            'reason' => 'Customer changed agent',
        ])->assertOk()
            ->assertJsonPath('data.authorization_status', PickupAuthorizationStatus::Revoked->value);

        $this->postJson("/api/v1/admin/orders/{$order->id}/customer-agent/schedule")
            ->assertStatus(422);
    }

    public function test_successful_release_pickup_handover_and_tracking_ownership(): void
    {
        ['order' => $order, 'user' => $user, 'admin' => $admin] = $this->createPaidChinaAgentOrder();
        $this->advanceToExportReady($order);

        $this->postJson("/api/v1/admin/orders/{$order->id}/customer-agent/authorize", [
            'agent_company' => 'Asha Logistics',
        ])->assertOk();

        $this->postJson("/api/v1/admin/orders/{$order->id}/customer-agent/schedule", [
            'scheduled_at' => now()->addDay()->toIso8601String(),
        ])->assertOk()
            ->assertJsonPath('data.release_status', WarehouseReleaseStatus::PickupScheduled->value);

        $this->postJson("/api/v1/admin/orders/{$order->id}/customer-agent/release", [
            'status' => WarehouseReleaseStatus::PickedUp->value,
        ])->assertOk();

        $this->postJson("/api/v1/admin/orders/{$order->id}/customer-agent/release", [
            'status' => WarehouseReleaseStatus::Released->value,
        ])->assertOk()
            ->assertJsonPath('data.release_status', WarehouseReleaseStatus::Released->value);

        // Duplicate release prevented.
        $this->postJson("/api/v1/admin/orders/{$order->id}/customer-agent/release", [
            'status' => WarehouseReleaseStatus::Released->value,
        ])->assertOk();
        $this->assertSame(
            1,
            CustomerAgentPickupHistory::query()
                ->where('order_id', $order->id)
                ->where('action', 'warehouse_release_released')
                ->count(),
        );

        $this->postJson("/api/v1/admin/orders/{$order->id}/customer-agent/arrive")->assertOk();

        $this->postJson("/api/v1/admin/orders/{$order->id}/customer-agent/handover", [
            'signature' => 'data:image/png;base64,abc',
            'reference_number' => 'PU-001',
            'document_number' => 'DOC-9',
            'notes' => 'Agent collected cartons',
            'photos' => ['https://cdn.example.com/pickup-1.jpg'],
        ])->assertOk()
            ->assertJsonPath('data.pickup_status', AgentPickupStatus::HandoverCompleted->value)
            ->assertJsonPath('tracking.tracking_ownership', 'customer_agent')
            ->assertJsonPath('tracking.company_transport_tracking', false);

        // Duplicate handover prevented.
        $this->postJson("/api/v1/admin/orders/{$order->id}/customer-agent/handover", [
            'notes' => 'retry',
        ])->assertOk();
        $this->assertSame(
            1,
            CustomerAgentPickupHistory::query()
                ->where('order_id', $order->id)
                ->where('action', 'handover_completed')
                ->count(),
        );

        $china = app(ChinaWorkflowEngine::class)->showForOrder($order->fresh());
        $this->assertSame(ChinaWorkflowStage::AgentHandedOff, $china->stage);

        Sanctum::actingAs($user);
        $tracking = $this->getJson("/api/v1/orders/{$order->id}/tracking")->assertOk();
        $this->assertSame('customer_agent_pickup', $tracking->json('data.source'));
        $this->assertSame('customer_agent', $tracking->json('data.tracking_ownership'));
        $this->assertFalse($tracking->json('data.company_transport_tracking'));
        $this->assertNull($tracking->json('data.shipment'));

        // Company shipment still blocked.
        Sanctum::actingAs($admin);
        $eligibility = app(ShipmentEligibilityService::class)->evaluate(
            $order->fresh()->fulfillment->fresh(['order.deliveryOption', 'warehouseJob', 'shipment'])
        );
        $this->assertFalse($eligibility['eligible']);
    }

    public function test_cashier_cannot_authorize_or_release(): void
    {
        ['order' => $order] = $this->createPaidChinaAgentOrder();
        $this->advanceToExportReady($order);

        $role = Role::query()->where('slug', 'store_cashier')->firstOrFail();
        $cashier = Admin::factory()->create([
            'role_id' => $role->id,
            'is_super_admin' => false,
        ]);
        Sanctum::actingAs($cashier);

        $this->postJson("/api/v1/admin/orders/{$order->id}/customer-agent/authorize")
            ->assertForbidden();
        $this->postJson("/api/v1/admin/orders/{$order->id}/customer-agent/release", [
            'status' => WarehouseReleaseStatus::ReadyForPickup->value,
        ])->assertForbidden();
    }

    public function test_company_shipping_path_unchanged(): void
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
        $this->mapSupplier($variant);

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

        $admin = Admin::factory()->superAdmin()->create();
        Sanctum::actingAs($admin);
        $this->advanceToExportReady($order->fresh());

        $fulfillment = $order->fresh()->fulfillment->fresh(['order.deliveryOption', 'warehouseJob', 'shipment']);
        $eligibility = app(ShipmentEligibilityService::class)->evaluate($fulfillment);
        $this->assertTrue($eligibility['eligible']);

        $this->postJson("/api/v1/admin/orders/{$order->id}/customer-agent/bootstrap")
            ->assertStatus(422);
    }

    public function test_audit_history_immutable_and_notifications_recorded(): void
    {
        ['order' => $order] = $this->createPaidChinaAgentOrder();
        $this->advanceToExportReady($order);

        $this->postJson("/api/v1/admin/orders/{$order->id}/customer-agent/authorize")->assertOk();
        $this->postJson("/api/v1/admin/orders/{$order->id}/customer-agent/handover", [
            'notes' => 'Collected',
            'signature' => 'sig',
        ])->assertOk();

        $history = CustomerAgentPickupHistory::query()
            ->where('order_id', $order->id)
            ->where('action', 'authorization_issued')
            ->first();
        $this->assertNotNull($history);
        $this->assertNotNull($history->idempotency_key);
        $this->assertNotNull($history->created_at);

        $this->assertDatabaseHas('notifications', [
            'event_type' => 'agent_pickup_authorized',
        ]);
        $this->assertDatabaseHas('notifications', [
            'event_type' => 'agent_handover_completed',
        ]);
    }
}
