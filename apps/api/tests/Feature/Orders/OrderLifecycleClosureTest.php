<?php

namespace Tests\Feature\Orders;

use App\Enums\FulfillmentStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentTransactionStatus;
use App\Models\DeliveryAddress;
use App\Models\Fulfillment;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\User;
use App\Services\Fulfillment\FulfillmentEngine;
use App\Services\Orders\Lifecycle\OrderLifecycleContext;
use App\Services\Orders\Lifecycle\OrderLifecycleEngine;
use App\Services\Payments\Orchestration\DTOs\PaymentProviderResult;
use App\Services\Payments\Orchestration\PaymentTransactionCompletionService;
use Database\Factories\Support\CatalogCartFixture;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Launch Closure #2 — Order Lifecycle authoritative transitions.
 */
class OrderLifecycleClosureTest extends TestCase
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
                    'id' => 'SESSION-LIFE-1',
                    'successIndicator' => 'indicator-life',
                    'checkoutUrl' => 'https://checkout.nmb.test/pay/life',
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
        ['product' => $product, 'variant' => $variant] = CatalogCartFixture::purchasable(20000);

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
            'unit_price' => 20000,
            'price_snapshot' => 20000,
            'currency' => 'TZS',
        ]);

        Sanctum::actingAs($user);
        ['order_id' => $orderId] = $this->createOrderWithShippingChoice([
            'shipping_choice' => 'customer_agent',
        ]);

        return ['user' => $user, 'order' => Order::query()->findOrFail($orderId)];
    }

    public function test_order_create_records_lifecycle_history(): void
    {
        ['order' => $order] = $this->createPendingPaymentOrder();

        $this->assertSame(OrderStatus::PendingPayment, $order->status);
        $this->assertDatabaseHas('order_status_history', [
            'order_id' => $order->id,
            'new_status' => OrderStatus::PendingPayment->value,
            'source' => 'order_engine',
        ]);
    }

    public function test_illegal_unpaid_to_delivered_is_rejected(): void
    {
        ['order' => $order] = $this->createPendingPaymentOrder();
        $engine = app(OrderLifecycleEngine::class);

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $engine->transition(
            $order,
            OrderStatus::Delivered,
            OrderLifecycleContext::system('test', 'illegal'),
        );
    }

    public function test_payment_success_marks_paid_with_history_once(): void
    {
        ['user' => $user, 'order' => $order] = $this->createPendingPaymentOrder();
        Sanctum::actingAs($user);

        $transactionId = $this->postJson("/api/v1/payments/start/{$order->id}")->assertCreated()->json('data.id');
        $transaction = \App\Models\PaymentTransaction::query()->findOrFail($transactionId);

        $service = app(PaymentTransactionCompletionService::class);
        $result = new PaymentProviderResult(
            ok: true,
            status: PaymentTransactionStatus::Successful,
            providerReference: $transaction->provider_reference,
            externalTransactionId: 'EXT-LIFE-1',
        );

        $service->applyResult($transaction, $result);
        $service->applyResult($transaction->fresh(), $result);

        $order->refresh();
        $this->assertSame(OrderStatus::Paid, $order->status);
        $this->assertNotNull($order->paid_at);
        $this->assertSame(1, Fulfillment::query()->where('order_id', $order->id)->count());

        $paidHistory = OrderStatusHistory::query()
            ->where('order_id', $order->id)
            ->where('new_status', OrderStatus::Paid->value)
            ->count();
        $this->assertSame(1, $paidHistory);
    }

    public function test_customer_can_cancel_unpaid_order(): void
    {
        ['user' => $user, 'order' => $order] = $this->createPendingPaymentOrder();
        Sanctum::actingAs($user);

        $this->postJson("/api/v1/orders/{$order->id}/cancel", ['reason' => 'Changed mind'])
            ->assertOk()
            ->assertJsonPath('data.status', OrderStatus::Cancelled->value);

        $this->assertDatabaseHas('order_status_history', [
            'order_id' => $order->id,
            'new_status' => OrderStatus::Cancelled->value,
            'source' => 'customer_cancel',
        ]);
    }

    public function test_paid_cancel_enters_refund_pending_not_silent_cancel(): void
    {
        ['user' => $user, 'order' => $order] = $this->createPendingPaymentOrder();
        Sanctum::actingAs($user);

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

        $order->refresh();
        $this->assertSame(OrderStatus::Paid, $order->status);

        $this->postJson("/api/v1/orders/{$order->id}/cancel", ['reason' => 'Need refund'])
            ->assertOk()
            ->assertJsonPath('data.status', OrderStatus::RefundPending->value);

        $this->assertNotNull($order->fresh()->paid_at);
    }

    public function test_fulfillment_processing_syncs_order_to_processing(): void
    {
        ['user' => $user, 'order' => $order] = $this->createPendingPaymentOrder();
        Sanctum::actingAs($user);

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

        $fulfillment = Fulfillment::query()->where('order_id', $order->id)->firstOrFail();
        app(FulfillmentEngine::class)->updateStatus($fulfillment, [
            'status' => FulfillmentStatus::Processing->value,
        ]);

        $this->assertSame(OrderStatus::Processing, $order->fresh()->status);
        $this->assertDatabaseHas('order_status_history', [
            'order_id' => $order->id,
            'new_status' => OrderStatus::Processing->value,
            'source' => 'fulfillment',
        ]);
    }

    public function test_completed_order_cannot_be_cancelled(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'status' => OrderStatus::Completed,
            'paid_at' => now(),
            'total' => 10000,
        ]);

        Sanctum::actingAs($user);
        $this->postJson("/api/v1/orders/{$order->id}/cancel")
            ->assertStatus(422);
    }

    public function test_duplicate_transition_is_idempotent(): void
    {
        ['order' => $order] = $this->createPendingPaymentOrder();
        $engine = app(OrderLifecycleEngine::class);

        $engine->transition(
            $order,
            OrderStatus::Cancelled,
            OrderLifecycleContext::system('test', 'first', 'cancel-key-1'),
        );
        $engine->transition(
            $order->fresh(),
            OrderStatus::Cancelled,
            OrderLifecycleContext::system('test', 'second', 'cancel-key-1'),
        );

        $this->assertSame(
            1,
            OrderStatusHistory::query()
                ->where('order_id', $order->id)
                ->where('new_status', OrderStatus::Cancelled->value)
                ->count(),
        );
    }

    public function test_refunded_cannot_reenter_processing(): void
    {
        $order = Order::factory()->create([
            'status' => OrderStatus::Refunded,
            'paid_at' => now(),
            'total' => 10000,
        ]);
        $engine = app(OrderLifecycleEngine::class);

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $engine->transition(
            $order,
            OrderStatus::Processing,
            OrderLifecycleContext::system('test', 'illegal reopen'),
        );
    }

    public function test_launch_closure_checkout_payment_still_passes_pipeline(): void
    {
        ['user' => $user, 'order' => $order] = $this->createPendingPaymentOrder();
        Sanctum::actingAs($user);

        $transactionId = $this->postJson("/api/v1/payments/start/{$order->id}")->assertCreated()->json('data.id');
        $transaction = \App\Models\PaymentTransaction::query()->findOrFail($transactionId);
        app(PaymentTransactionCompletionService::class)->applyResult(
            $transaction,
            new PaymentProviderResult(
                ok: true,
                status: PaymentTransactionStatus::Successful,
                providerReference: $transaction->provider_reference,
            ),
        );

        $this->assertSame(OrderStatus::Paid, $order->fresh()->status);
        $this->assertSame(1, Fulfillment::query()->where('order_id', $order->id)->count());
        $this->assertSame(1, \App\Models\WarehouseJob::query()->count());
    }
}
