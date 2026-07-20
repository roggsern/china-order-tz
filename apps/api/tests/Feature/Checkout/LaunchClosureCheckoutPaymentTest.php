<?php

namespace Tests\Feature\Checkout;

use App\Enums\CartStatus;
use App\Enums\DeliveryType;
use App\Enums\FulfillmentStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentTransactionStatus;
use App\Enums\ShippingMethod;
use App\Enums\WarehouseJobStatus;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\DeliveryAddress;
use App\Models\DeliveryOption;
use App\Models\Fulfillment;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentTransaction;
use App\Models\ProductShippingOption;
use App\Models\User;
use App\Models\WarehouseJob;
use App\Services\Payments\Orchestration\DTOs\PaymentProviderResult;
use App\Services\Payments\Orchestration\PaymentTransactionCompletionService;
use Database\Factories\Support\CatalogCartFixture;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Launch Closure #1 — shipping before payment:
 * cart → checkout/start → shipping-choice → from-checkout → payments/start → paid → fulfillment → warehouse.
 */
class LaunchClosureCheckoutPaymentTest extends TestCase
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
                    'id' => 'SESSION-CLOSURE-1',
                    'successIndicator' => 'indicator-closure',
                    'checkoutUrl' => 'https://checkout.nmb.test/pay/closure',
                ],
            ]),
            'sandbox.nmb.test/*/order/*' => Http::response([
                'result' => 'PENDING',
                'order' => [
                    'id' => 'pending',
                    'amount' => '0.00',
                    'currency' => 'TZS',
                ],
            ]),
        ]);
    }

    private function seedReadyCustomer(): array
    {
        $user = User::factory()->create();
        DeliveryAddress::factory()->create(['user_id' => $user->id]);

        ['product' => $product, 'variant' => $variant] = CatalogCartFixture::purchasable(25000);

        $cart = Cart::factory()->create([
            'user_id' => $user->id,
            'status' => CartStatus::Active,
            'currency' => 'TZS',
        ]);

        CartItem::factory()->create([
            'cart_id' => $cart->id,
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'quantity' => 2,
            'unit_price' => 25000,
            'price_snapshot' => 25000,
            'currency' => 'TZS',
        ]);

        return compact('user', 'cart', 'product', 'variant');
    }

    private function seedChinaCompanyCart(float $unit = 50000, float $air = 8000, int $qty = 2): array
    {
        $user = User::factory()->create();
        DeliveryAddress::factory()->create(['user_id' => $user->id]);

        ['product' => $product, 'variant' => $variant] = CatalogCartFixture::purchasable($unit);
        $product->update([
            'fulfillment_source' => 'imported_from_china',
            'air_shipping_price' => $air,
        ]);
        ProductShippingOption::query()->where('product_id', $product->id)->forceDelete();
        ProductShippingOption::factory()->air($air)->create(['product_id' => $product->id]);

        $cart = Cart::factory()->create([
            'user_id' => $user->id,
            'status' => CartStatus::Active,
            'currency' => 'TZS',
        ]);
        CartItem::factory()->create([
            'cart_id' => $cart->id,
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'quantity' => $qty,
            'unit_price' => $unit,
            'price_snapshot' => $unit,
            'currency' => 'TZS',
        ]);

        return compact('user', 'cart', 'product', 'variant');
    }

    public function test_official_pipeline_checkout_payment_fulfillment_warehouse(): void
    {
        ['user' => $user] = $this->seedReadyCustomer();
        Sanctum::actingAs($user);

        ['order_id' => $orderId] = $this->createOrderWithShippingChoice([
            'shipping_choice' => DeliveryType::CustomerAgent->value,
        ]);

        $this->assertDatabaseHas('orders', [
            'id' => $orderId,
            'status' => OrderStatus::PendingPayment->value,
        ]);
        $this->assertDatabaseHas('delivery_options', [
            'order_id' => $orderId,
            'delivery_type' => DeliveryType::CustomerAgent->value,
        ]);

        $transactionId = $this->postJson("/api/v1/payments/start/{$orderId}")
            ->assertCreated()
            ->assertJsonPath('data.provider', 'nmb')
            ->json('data.id');

        $order = Order::query()->findOrFail($orderId);
        $this->assertSame(OrderStatus::PendingPayment, $order->status);
        $this->assertNull($order->paid_at);

        $transaction = PaymentTransaction::query()->findOrFail($transactionId);
        $completed = app(PaymentTransactionCompletionService::class)->applyResult(
            $transaction,
            new PaymentProviderResult(
                ok: true,
                status: PaymentTransactionStatus::Successful,
                providerReference: $transaction->provider_reference,
                externalTransactionId: 'EXT-CLOSURE-1',
                checkoutUrl: $transaction->checkout_url,
                successIndicator: $transaction->success_indicator,
            ),
        );

        $this->assertSame(PaymentTransactionStatus::Successful, $completed->status);

        $order->refresh();
        $this->assertSame(OrderStatus::Paid, $order->status);
        $this->assertNotNull($order->paid_at);

        $fulfillment = Fulfillment::query()->where('order_id', $orderId)->first();
        $this->assertNotNull($fulfillment);
        $this->assertSame(FulfillmentStatus::Pending, $fulfillment->status);

        $job = WarehouseJob::query()->where('fulfillment_id', $fulfillment->id)->first();
        $this->assertNotNull($job);
        $this->assertSame(WarehouseJobStatus::Pending, $job->status);
    }

    public function test_company_shipping_selected_before_payment_and_flows_to_payment(): void
    {
        ['user' => $user] = $this->seedChinaCompanyCart();
        Sanctum::actingAs($user);

        $session = $this->postJson('/api/v1/checkout/start')->assertCreated()->json('data');

        $this->postJson("/api/v1/checkout/{$session['id']}/shipping-choice", [
            'shipping_choice' => DeliveryType::CompanyShipping->value,
            'shipping_method' => 'air',
        ])
            ->assertOk()
            ->assertJsonPath('data.shipping_choice', 'company_shipping')
            ->assertJsonPath('data.shipping_method', 'air')
            ->assertJsonPath('data.shipping_total', '16000.00')
            ->assertJsonPath('data.grand_total', '116000.00');

        $order = $this->postJson("/api/v1/orders/from-checkout/{$session['id']}")
            ->assertCreated()
            ->assertJsonPath('data.shipping_total', '16000.00')
            ->assertJsonPath('data.grand_total', '116000.00')
            ->json('data');

        $this->assertDatabaseHas('delivery_options', [
            'order_id' => $order['id'],
            'delivery_type' => DeliveryType::CompanyShipping->value,
            'shipping_method' => 'air',
        ]);

        $this->postJson("/api/v1/payments/start/{$order['id']}")
            ->assertCreated()
            ->assertJsonPath('data.amount', '116000.00');
    }

    public function test_customer_agent_explicitly_selected_before_payment_with_zero_shipping(): void
    {
        ['user' => $user] = $this->seedReadyCustomer();
        Sanctum::actingAs($user);

        $sessionId = $this->postJson('/api/v1/checkout/start')->json('data.id');

        $this->postJson("/api/v1/checkout/{$sessionId}/shipping-choice", [
            'shipping_choice' => DeliveryType::CustomerAgent->value,
        ])
            ->assertOk()
            ->assertJsonPath('data.shipping_choice', 'customer_agent')
            ->assertJsonPath('data.shipping_total', '0.00')
            ->assertJsonPath('data.grand_total', '50000.00');

        $order = $this->postJson("/api/v1/orders/from-checkout/{$sessionId}")
            ->assertCreated()
            ->assertJsonPath('data.shipping_total', '0.00')
            ->assertJsonPath('data.grand_total', '50000.00')
            ->json('data');

        $this->assertSame(
            DeliveryType::CustomerAgent,
            DeliveryOption::query()->where('order_id', $order['id'])->first()?->delivery_type,
        );

        $this->postJson("/api/v1/payments/start/{$order['id']}")
            ->assertCreated()
            ->assertJsonPath('data.amount', '50000.00');
    }

    public function test_payment_cannot_start_without_shipping_choice_on_order(): void
    {
        ['user' => $user] = $this->seedReadyCustomer();
        Sanctum::actingAs($user);

        $order = Order::factory()->create([
            'user_id' => $user->id,
            'status' => OrderStatus::PendingPayment,
            'total' => 50000,
            'shipping_amount' => 0,
        ]);

        $this->postJson("/api/v1/payments/start/{$order->id}")
            ->assertStatus(422)
            ->assertJsonValidationErrors(['shipping_choice']);
    }

    public function test_order_cannot_be_created_without_shipping_choice(): void
    {
        ['user' => $user] = $this->seedReadyCustomer();
        Sanctum::actingAs($user);

        $sessionId = $this->postJson('/api/v1/checkout/start')->json('data.id');

        $this->postJson("/api/v1/orders/from-checkout/{$sessionId}")
            ->assertStatus(422)
            ->assertJsonValidationErrors(['shipping_choice']);
    }

    public function test_company_shipping_without_valid_price_is_rejected(): void
    {
        $user = User::factory()->create();
        DeliveryAddress::factory()->create(['user_id' => $user->id]);
        ['product' => $product, 'variant' => $variant] = CatalogCartFixture::purchasable(25000);
        $product->update(['fulfillment_source' => 'imported_from_china']);
        ProductShippingOption::query()->where('product_id', $product->id)->forceDelete();

        $cart = Cart::factory()->create([
            'user_id' => $user->id,
            'status' => CartStatus::Active,
            'currency' => 'TZS',
        ]);
        CartItem::factory()->create([
            'cart_id' => $cart->id,
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'quantity' => 1,
            'unit_price' => 25000,
            'price_snapshot' => 25000,
            'currency' => 'TZS',
        ]);

        Sanctum::actingAs($user);

        $sessionId = $this->postJson('/api/v1/checkout/start')->json('data.id');

        $this->postJson("/api/v1/checkout/{$sessionId}/shipping-choice", [
            'shipping_choice' => DeliveryType::CompanyShipping->value,
            'shipping_method' => 'air',
        ])->assertStatus(422);
    }

    public function test_stale_checkout_after_cart_mutation_is_rejected(): void
    {
        ['user' => $user, 'cart' => $cart] = $this->seedReadyCustomer();
        Sanctum::actingAs($user);

        $sessionId = $this->postJson('/api/v1/checkout/start')->json('data.id');
        $this->applyCheckoutShippingChoice($sessionId, [
            'shipping_choice' => DeliveryType::CustomerAgent->value,
        ]);

        CartItem::query()->where('cart_id', $cart->id)->update(['quantity' => 3]);

        $this->postJson("/api/v1/orders/from-checkout/{$sessionId}")
            ->assertStatus(422)
            ->assertJsonValidationErrors(['session']);
    }

    public function test_confirm_facade_requires_explicit_shipping_choice(): void
    {
        ['user' => $user] = $this->seedReadyCustomer();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/orders/confirm')->assertStatus(422);

        $orderId = $this->postJson('/api/v1/orders/confirm', [
            'shipping_choice' => DeliveryType::CustomerAgent->value,
        ])
            ->assertCreated()
            ->assertJsonPath('data.order.status', OrderStatus::PendingPayment->value)
            ->json('data.order.id');

        $this->assertNotNull(Order::query()->find($orderId)?->checkout_session_id);
        $this->assertDatabaseHas('delivery_options', [
            'order_id' => $orderId,
            'delivery_type' => DeliveryType::CustomerAgent->value,
        ]);
    }

    public function test_duplicate_successful_payment_is_idempotent_and_keeps_fulfillment(): void
    {
        ['user' => $user] = $this->seedReadyCustomer();
        Sanctum::actingAs($user);

        ['order_id' => $orderId] = $this->createOrderWithShippingChoice([
            'shipping_choice' => DeliveryType::CustomerAgent->value,
        ]);
        $transactionId = $this->postJson("/api/v1/payments/start/{$orderId}")->json('data.id');

        $transaction = PaymentTransaction::query()->findOrFail($transactionId);
        $service = app(PaymentTransactionCompletionService::class);
        $result = new PaymentProviderResult(
            ok: true,
            status: PaymentTransactionStatus::Successful,
            providerReference: $transaction->provider_reference,
            externalTransactionId: 'EXT-DUP-1',
        );

        $service->applyResult($transaction, $result);
        $service->applyResult($transaction->fresh(), $result);

        $this->assertSame(1, Fulfillment::query()->where('order_id', $orderId)->count());
        $this->assertSame(1, WarehouseJob::query()->count());
        $this->assertSame(OrderStatus::Paid, Order::query()->find($orderId)->status);
    }

    public function test_failed_payment_does_not_create_fulfillment(): void
    {
        ['user' => $user] = $this->seedReadyCustomer();
        Sanctum::actingAs($user);

        ['order_id' => $orderId] = $this->createOrderWithShippingChoice([
            'shipping_choice' => DeliveryType::CustomerAgent->value,
        ]);
        $transactionId = $this->postJson("/api/v1/payments/start/{$orderId}")->json('data.id');

        $transaction = PaymentTransaction::query()->findOrFail($transactionId);
        app(PaymentTransactionCompletionService::class)->applyResult(
            $transaction,
            new PaymentProviderResult(
                ok: false,
                status: PaymentTransactionStatus::Failed,
                providerReference: $transaction->provider_reference,
            ),
        );

        $order = Order::query()->findOrFail($orderId);
        $this->assertSame(OrderStatus::PendingPayment, $order->status);
        $this->assertNull($order->paid_at);
        $this->assertSame(0, Fulfillment::query()->where('order_id', $orderId)->count());
        $this->assertSame(0, WarehouseJob::query()->count());
    }

    public function test_company_shipping_method_without_price_on_cart_line_is_rejected_at_start(): void
    {
        $user = User::factory()->create();
        DeliveryAddress::factory()->create(['user_id' => $user->id]);
        ['product' => $product, 'variant' => $variant] = CatalogCartFixture::purchasable(25000);
        $product->update(['fulfillment_source' => 'imported_from_china']);

        $cart = Cart::factory()->create([
            'user_id' => $user->id,
            'status' => CartStatus::Active,
            'currency' => 'TZS',
        ]);
        CartItem::factory()->create([
            'cart_id' => $cart->id,
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'quantity' => 1,
            'unit_price' => 25000,
            'price_snapshot' => 25000,
            'currency' => 'TZS',
            'shipping_method' => ShippingMethod::Air,
            'shipping_price' => null,
        ]);

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/checkout/start')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['shipping']);
    }

    public function test_legacy_payment_initiate_is_retired(): void
    {
        $user = User::factory()->create();
        $payment = Payment::factory()->nmb()->create([
            'user_id' => $user->id,
        ]);

        Sanctum::actingAs($user);

        $this->postJson("/api/v1/payments/{$payment->id}/initiate")
            ->assertStatus(410)
            ->assertJsonPath('deprecated', true)
            ->assertJsonPath('replacement', '/api/v1/payments/start/{order}');
    }

    public function test_tz_local_self_pickup_before_payment(): void
    {
        $user = User::factory()->create();
        DeliveryAddress::factory()->create(['user_id' => $user->id]);
        ['product' => $product, 'variant' => $variant] = CatalogCartFixture::purchasable(15000);
        $product->update([
            'fulfillment_source' => 'buy_from_tz',
            'commerce_channel_id' => \App\Models\CommerceChannel::query()
                ->where('code', 'TZ_LOCAL')
                ->value('id'),
        ]);

        $cart = Cart::factory()->create([
            'user_id' => $user->id,
            'status' => CartStatus::Active,
            'currency' => 'TZS',
        ]);
        CartItem::factory()->create([
            'cart_id' => $cart->id,
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'quantity' => 1,
            'unit_price' => 15000,
            'price_snapshot' => 15000,
            'currency' => 'TZS',
        ]);

        Sanctum::actingAs($user);

        ['order_id' => $orderId] = $this->createOrderWithShippingChoice([
            'shipping_choice' => DeliveryType::SelfPickup->value,
        ]);

        $this->assertDatabaseHas('delivery_options', [
            'order_id' => $orderId,
            'delivery_type' => DeliveryType::SelfPickup->value,
        ]);
        $this->assertDatabaseHas('orders', [
            'id' => $orderId,
            'shipping_amount' => '0.00',
            'total' => '15000.00',
        ]);

        $this->postJson("/api/v1/payments/start/{$orderId}")
            ->assertCreated()
            ->assertJsonPath('data.amount', '15000.00');
    }
}
