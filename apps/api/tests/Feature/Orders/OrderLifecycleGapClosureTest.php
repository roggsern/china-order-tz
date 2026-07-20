<?php

namespace Tests\Feature\Orders;

use App\Enums\CommerceChannelCode;
use App\Enums\OrderStatus;
use App\Enums\PaymentTransactionStatus;
use App\Enums\PosPaymentHandler;
use App\Enums\RefundTransactionStatus;
use App\Enums\VariantPriceType;
use App\Models\Admin;
use App\Models\CommerceChannel;
use App\Models\DeliveryAddress;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\PaymentMethodDefinition;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\RefundTransaction;
use App\Models\Role;
use App\Models\User;
use App\Models\VariantInventory;
use App\Models\VariantPrice;
use App\Services\Orders\Lifecycle\OrderLifecycleContext;
use App\Services\Orders\Lifecycle\OrderLifecycleEngine;
use App\Services\Payments\Orchestration\DTOs\PaymentProviderResult;
use App\Services\Payments\Orchestration\PaymentTransactionCompletionService;
use App\Services\Stores\StoreAssignmentService;
use App\Services\Stores\StoreService;
use Database\Factories\Support\CatalogCartFixture;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Launch Closure #2 gap closure — web admin authority, POS history, refund_pending ops.
 */
class OrderLifecycleGapClosureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

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
                    'id' => 'SESSION-GAP-1',
                    'successIndicator' => 'indicator-gap',
                    'checkoutUrl' => 'https://checkout.nmb.test/pay/gap',
                ],
            ]),
            'sandbox.nmb.test/*/order/*' => Http::response([
                'result' => 'PENDING',
                'order' => ['id' => 'pending', 'amount' => '0.00', 'currency' => 'TZS'],
            ]),
        ]);
    }

    private function createPendingPaymentOrder(): array
    {
        $user = User::factory()->create();
        DeliveryAddress::factory()->create(['user_id' => $user->id]);
        ['product' => $product, 'variant' => $variant] = CatalogCartFixture::purchasable(25000);

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
            'unit_price' => 25000,
            'price_snapshot' => 25000,
            'currency' => 'TZS',
        ]);

        Sanctum::actingAs($user);
        ['order_id' => $orderId] = $this->createOrderWithShippingChoice([
            'shipping_choice' => 'customer_agent',
        ]);

        return ['user' => $user, 'order' => Order::query()->findOrFail($orderId)];
    }

    private function markPaid(Order $order): Order
    {
        Sanctum::actingAs($order->user);
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

        return $order->fresh();
    }

    public function test_admin_has_no_direct_status_mutation_endpoint(): void
    {
        $admin = Admin::factory()->create();
        $order = Order::factory()->create(['status' => OrderStatus::Paid, 'paid_at' => now()]);

        Sanctum::actingAs($admin);
        $this->patchJson("/api/v1/admin/orders/{$order->id}/status", [
            'status' => OrderStatus::Delivered->value,
        ])->assertNotFound();

        $this->putJson("/api/v1/admin/orders/{$order->id}", [
            'status' => OrderStatus::Delivered->value,
        ])->assertMethodNotAllowed();

        $this->assertSame(OrderStatus::Paid, $order->fresh()->status);
    }

    public function test_admin_illegal_transition_via_lifecycle_is_rejected(): void
    {
        $order = Order::factory()->create([
            'status' => OrderStatus::PendingPayment,
            'paid_at' => null,
        ]);

        $this->expectException(\Illuminate\Validation\ValidationException::class);

        app(OrderLifecycleEngine::class)->transition(
            $order,
            OrderStatus::Delivered,
            OrderLifecycleContext::system('admin', 'illegal shortcut'),
        );
    }

    public function test_admin_and_customer_map_same_backend_status_labels(): void
    {
        ['user' => $user, 'order' => $order] = $this->createPendingPaymentOrder();
        $order = $this->markPaid($order);

        Sanctum::actingAs($user);
        $customerPayload = $this->getJson("/api/v1/orders/{$order->id}")
            ->assertOk()
            ->json('data');

        $admin = Admin::factory()->create();
        Sanctum::actingAs($admin);
        $adminStatus = $this->getJson("/api/v1/admin/orders/{$order->id}")
            ->assertOk()
            ->json('data.status');

        $this->assertSame(OrderStatus::Paid->value, $customerPayload['status']);
        $this->assertSame($customerPayload['status'], $adminStatus);
        $this->assertSame(OrderStatus::Paid->customerLabel(), $customerPayload['status_label']);
    }

    public function test_admin_refresh_preserves_authoritative_status_after_cancel(): void
    {
        ['user' => $user, 'order' => $order] = $this->createPendingPaymentOrder();
        $order = $this->markPaid($order);

        Sanctum::actingAs($user);
        $this->postJson("/api/v1/orders/{$order->id}/cancel", ['reason' => 'Changed mind'])
            ->assertOk()
            ->assertJsonPath('data.status', OrderStatus::RefundPending->value);

        $admin = Admin::factory()->create();
        Sanctum::actingAs($admin);

        $first = $this->getJson("/api/v1/admin/orders/{$order->id}")->assertOk()->json('data.status');
        $second = $this->getJson("/api/v1/admin/orders/{$order->id}")->assertOk()->json('data.status');

        $this->assertSame(OrderStatus::RefundPending->value, $first);
        $this->assertSame($first, $second);

        Sanctum::actingAs($user);
        $this->assertSame(
            'Refund in progress',
            $this->getJson("/api/v1/orders/{$order->id}")->json('data.status_label'),
        );
    }

    public function test_pos_sale_records_initial_lifecycle_history_with_source_pos(): void
    {
        $this->seed(RoleSeeder::class);
        $stores = app(StoreService::class);
        $assignments = app(StoreAssignmentService::class);

        CommerceChannel::query()->updateOrCreate(
            ['code' => CommerceChannelCode::TzLocal->value],
            ['name' => 'Buy From TZ', 'description' => 'Local', 'is_active' => true],
        );

        PaymentMethodDefinition::query()->updateOrCreate(
            ['code' => 'CASH'],
            [
                'name' => 'Cash',
                'is_active' => true,
                'sort_order' => 1,
                'config' => ['handler' => PosPaymentHandler::CashWithChange->value, 'pos_enabled' => true],
            ],
        );

        $store = $stores->create(['code' => 'ZION', 'name' => 'Zion']);
        $super = Admin::factory()->superAdmin()->create();
        $cashier = Admin::factory()->create([
            'role_id' => Role::query()->where('slug', 'store_cashier')->value('id'),
            'is_super_admin' => false,
        ]);
        $assignments->assign($cashier, $store, $super);

        $tz = CommerceChannel::query()->where('code', CommerceChannelCode::TzLocal->value)->firstOrFail();
        $product = Product::factory()->create([
            'store_id' => $store->id,
            'commerce_channel_id' => $tz->id,
            'fulfillment_source' => CommerceChannelCode::TzLocal->fulfillmentSource(),
            'price' => 50000,
            'is_active' => true,
            'is_demo' => false,
        ]);
        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'price' => 50000,
            'is_active' => true,
        ]);
        VariantPrice::query()->create([
            'product_variant_id' => $variant->id,
            'price_type' => VariantPriceType::Retail,
            'currency' => 'TZS',
            'amount' => 50000,
            'minimum_quantity' => 1,
            'is_active' => true,
        ]);
        VariantInventory::query()->create([
            'product_variant_id' => $variant->id,
            'inventory_location_id' => $store->defaultInventoryLocation->id,
            'warehouse_code' => $store->defaultInventoryLocation->code,
            'on_hand' => 10,
            'reserved' => 0,
            'is_active' => true,
        ]);

        Sanctum::actingAs($cashier);
        $this->postJson('/api/v1/admin/pos/sessions/open', [
            'store_id' => $store->id,
            'terminal_id' => $store->terminals()->firstOrFail()->id,
            'opening_float' => 100000,
        ])->assertCreated();

        $key = 'pos-gap-'.uniqid();
        $sale = $this->postJson('/api/v1/admin/pos/sales', [
            'items' => [[
                'product_id' => $product->id,
                'product_variant_id' => $variant->id,
                'quantity' => 1,
            ]],
            'payment_method' => 'CASH',
            'amount_received' => 50000,
            'idempotency_key' => $key,
        ])->assertCreated();

        $orderId = $sale->json('data.order.id');
        $this->assertSame(OrderStatus::Paid->value, $sale->json('data.order.status'));

        $this->assertDatabaseHas('order_status_history', [
            'order_id' => $orderId,
            'previous_status' => null,
            'new_status' => OrderStatus::Paid->value,
            'source' => 'pos',
            'actor_type' => 'admin',
            'changed_by_admin_id' => $cashier->id,
            'idempotency_key' => 'pos-created:'.$key,
        ]);

        $history = OrderStatusHistory::query()->where('order_id', $orderId)->first();
        $this->assertTrue((bool) data_get($history->metadata, 'immediate_paid'));
        $this->assertNotEmpty(data_get($history->metadata, 'payment_id'));

        // Duplicate POS submission is idempotent — no second order/history.
        $replay = $this->postJson('/api/v1/admin/pos/sales', [
            'items' => [[
                'product_id' => $product->id,
                'product_variant_id' => $variant->id,
                'quantity' => 1,
            ]],
            'payment_method' => 'CASH',
            'amount_received' => 50000,
            'idempotency_key' => $key,
        ])->assertOk();

        $this->assertSame($orderId, $replay->json('data.order.id'));
        $this->assertSame(1, Order::query()->where('id', $orderId)->count());
        $this->assertSame(
            1,
            OrderStatusHistory::query()->where('order_id', $orderId)->where('source', 'pos')->count(),
        );
        $this->assertNull(\App\Models\Fulfillment::query()->where('order_id', $orderId)->first());
    }

    public function test_refund_pending_manual_admin_completion_workflow(): void
    {
        ['user' => $user, 'order' => $order] = $this->createPendingPaymentOrder();
        $order = $this->markPaid($order);
        $amount = number_format((float) ($order->fresh()->total ?? $order->fresh()->grand_total), 2, '.', '');

        Sanctum::actingAs($user);
        $this->postJson("/api/v1/orders/{$order->id}/cancel", ['reason' => 'Need refund'])
            ->assertOk()
            ->assertJsonPath('data.status', OrderStatus::RefundPending->value);

        $this->assertDatabaseHas('refund_transactions', [
            'order_id' => $order->id,
            'return_request_id' => null,
            'status' => RefundTransactionStatus::Pending->value,
            'method' => 'manual_cancellation',
        ]);

        $admin = Admin::factory()->create();
        Sanctum::actingAs($admin);

        $this->getJson('/api/v1/admin/orders?status=refund_pending')
            ->assertOk()
            ->assertJsonFragment(['id' => $order->id]);

        // Partial refunds rejected at launch.
        $this->postJson("/api/v1/admin/orders/{$order->id}/refunds/complete", [
            'amount' => ((float) $amount) / 2,
            'reference' => 'MPESA-PARTIAL',
            'confirm' => true,
        ])->assertStatus(422);

        $this->postJson("/api/v1/admin/orders/{$order->id}/refunds/complete", [
            'amount' => $amount,
            'reference' => 'MPESA-REF-001',
            'notes' => 'Refunded via M-Pesa',
            'reason' => 'Customer cancellation',
            'confirm' => true,
        ])->assertOk()
            ->assertJsonPath('data.order.status', OrderStatus::Refunded->value)
            ->assertJsonPath('data.refund.status', RefundTransactionStatus::Completed->value);

        $this->assertSame(OrderStatus::Refunded, $order->fresh()->status);
        $this->assertDatabaseHas('order_status_history', [
            'order_id' => $order->id,
            'new_status' => OrderStatus::Refunded->value,
            'source' => 'refund',
        ]);

        // Duplicate completion is idempotent.
        $this->postJson("/api/v1/admin/orders/{$order->id}/refunds/complete", [
            'amount' => $amount,
            'reference' => 'MPESA-REF-001',
            'confirm' => true,
        ])->assertOk()
            ->assertJsonPath('data.order.status', OrderStatus::Refunded->value);

        $this->assertSame(
            1,
            RefundTransaction::query()
                ->where('order_id', $order->id)
                ->where('status', RefundTransactionStatus::Completed->value)
                ->count(),
        );
    }

    public function test_failed_cancellation_refund_keeps_refund_pending(): void
    {
        ['user' => $user, 'order' => $order] = $this->createPendingPaymentOrder();
        $order = $this->markPaid($order);

        Sanctum::actingAs($user);
        $this->postJson("/api/v1/orders/{$order->id}/cancel")->assertOk();

        $admin = Admin::factory()->create();
        Sanctum::actingAs($admin);

        $this->postJson("/api/v1/admin/orders/{$order->id}/refunds/fail", [
            'reason' => 'Bank transfer rejected',
        ])->assertOk()
            ->assertJsonPath('data.order.status', OrderStatus::RefundPending->value)
            ->assertJsonPath('data.refund.status', RefundTransactionStatus::Failed->value);

        $this->assertSame(OrderStatus::RefundPending, $order->fresh()->status);
    }

    public function test_pos_paid_cancel_follows_same_refund_pending_rules(): void
    {
        $this->seed(RoleSeeder::class);
        $stores = app(StoreService::class);
        $assignments = app(StoreAssignmentService::class);
        CommerceChannel::query()->updateOrCreate(
            ['code' => CommerceChannelCode::TzLocal->value],
            ['name' => 'Buy From TZ', 'description' => 'Local', 'is_active' => true],
        );
        PaymentMethodDefinition::query()->updateOrCreate(
            ['code' => 'CASH'],
            [
                'name' => 'Cash',
                'is_active' => true,
                'sort_order' => 1,
                'config' => ['handler' => PosPaymentHandler::CashWithChange->value, 'pos_enabled' => true],
            ],
        );

        $store = $stores->create(['code' => 'PEACHY', 'name' => 'Peachy']);
        $super = Admin::factory()->superAdmin()->create();
        $cashier = Admin::factory()->create([
            'role_id' => Role::query()->where('slug', 'store_cashier')->value('id'),
            'is_super_admin' => false,
        ]);
        $assignments->assign($cashier, $store, $super);
        $tz = CommerceChannel::query()->where('code', CommerceChannelCode::TzLocal->value)->firstOrFail();
        $product = Product::factory()->create([
            'store_id' => $store->id,
            'commerce_channel_id' => $tz->id,
            'fulfillment_source' => CommerceChannelCode::TzLocal->fulfillmentSource(),
            'price' => 20000,
            'is_active' => true,
            'is_demo' => false,
        ]);
        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'price' => 20000,
            'is_active' => true,
        ]);
        VariantPrice::query()->create([
            'product_variant_id' => $variant->id,
            'price_type' => VariantPriceType::Retail,
            'currency' => 'TZS',
            'amount' => 20000,
            'minimum_quantity' => 1,
            'is_active' => true,
        ]);
        VariantInventory::query()->create([
            'product_variant_id' => $variant->id,
            'inventory_location_id' => $store->defaultInventoryLocation->id,
            'warehouse_code' => $store->defaultInventoryLocation->code,
            'on_hand' => 5,
            'reserved' => 0,
            'is_active' => true,
        ]);

        Sanctum::actingAs($cashier);
        $this->postJson('/api/v1/admin/pos/sessions/open', [
            'store_id' => $store->id,
            'terminal_id' => $store->terminals()->firstOrFail()->id,
            'opening_float' => 50000,
        ])->assertCreated();

        $orderId = $this->postJson('/api/v1/admin/pos/sales', [
            'items' => [[
                'product_id' => $product->id,
                'product_variant_id' => $variant->id,
                'quantity' => 1,
            ]],
            'payment_method' => 'CASH',
            'amount_received' => 20000,
        ])->assertCreated()->json('data.order.id');

        $this->patchJson("/api/v1/admin/orders/{$orderId}/cancel")
            ->assertOk()
            ->assertJsonPath('data.status', OrderStatus::RefundPending->value);

        $this->assertDatabaseHas('refund_transactions', [
            'order_id' => $orderId,
            'return_request_id' => null,
            'status' => RefundTransactionStatus::Pending->value,
        ]);
    }
}
