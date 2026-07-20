<?php

namespace Tests\Feature\Payments;

use App\Enums\OrderStatus;
use App\Enums\PaymentProvider;
use App\Enums\PaymentTransactionStatus;
use App\Models\Admin;
use App\Models\Order;
use App\Models\PaymentTransaction;
use App\Models\User;
use App\Services\Payments\Orchestration\PaymentOrchestrator;
use App\Services\Payments\Orchestration\Providers\NmbPaymentProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PaymentOrchestratorTest extends TestCase
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
                    'id' => 'SESSION-ORCH-1',
                    'successIndicator' => 'indicator-orch',
                    'checkoutUrl' => 'https://checkout.nmb.test/pay/orch',
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

    public function test_starts_payment_transaction_for_pending_payment_order(): void
    {
        $user = User::factory()->create();
        $order = $this->createPayableOrder($user, [
            'total' => 45000,
            'currency' => 'TZS',
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson("/api/v1/payments/start/{$order->id}")
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.provider', 'nmb')
            ->assertJsonPath('data.status', 'processing')
            ->assertJsonPath('data.amount', '45000.00')
            ->assertJsonPath('data.currency', 'TZS')
            ->assertJsonPath('data.order_id', $order->id)
            ->assertJsonPath('data.provider_reference', 'SESSION-ORCH-1')
            ->assertJsonPath('data.checkout_url', 'https://checkout.nmb.test/pay/orch');

        $merchantReference = $response->json('data.merchant_reference');
        $this->assertMatchesRegularExpression('/^COTZ-PAY-\d{8}-\d{6}$/', $merchantReference);
        $this->assertNotEmpty($response->json('data.provider_reference'));
        $this->assertNotNull($response->json('data.request_payload'));
        $this->assertNotNull($response->json('data.response_payload'));

        // Must not mark order paid on initiation.
        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => OrderStatus::PendingPayment->value,
        ]);
        $this->assertNull($order->fresh()->paid_at);
    }

    public function test_rejects_non_pending_payment_order(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'status' => OrderStatus::Paid,
            'total' => 10000,
        ]);

        Sanctum::actingAs($user);

        $this->postJson("/api/v1/payments/start/{$order->id}")
            ->assertStatus(422)
            ->assertJsonValidationErrors(['order']);
    }

    public function test_shows_payment_transaction(): void
    {
        $user = User::factory()->create();
        $order = $this->createPayableOrder($user, [
            'total' => 12000,
        ]);

        Sanctum::actingAs($user);
        $transactionId = $this->postJson("/api/v1/payments/start/{$order->id}")->json('data.id');

        $this->getJson("/api/v1/payments/{$transactionId}")
            ->assertOk()
            ->assertJsonPath('data.id', $transactionId)
            ->assertJsonPath('data.order.order_number', $order->order_number);
    }

    public function test_refreshes_payment_transaction_without_marking_order_paid_when_still_pending(): void
    {
        $user = User::factory()->create();
        $order = $this->createPayableOrder($user, [
            'total' => 15000,
        ]);

        Sanctum::actingAs($user);
        $transactionId = $this->postJson("/api/v1/payments/start/{$order->id}")->json('data.id');

        $this->postJson("/api/v1/payments/{$transactionId}/refresh")
            ->assertOk()
            ->assertJsonPath('data.status', 'processing');

        $this->assertSame(OrderStatus::PendingPayment, $order->fresh()->status);
        $this->assertNull($order->fresh()->paid_at);
    }

    public function test_merchant_reference_is_unique(): void
    {
        $user = User::factory()->create();
        $orderA = $this->createPayableOrder($user, [
            'total' => 10000,
        ]);
        $orderB = $this->createPayableOrder($user, [
            'total' => 20000,
        ]);

        Sanctum::actingAs($user);

        $responseA = $this->postJson("/api/v1/payments/start/{$orderA->id}")->assertCreated();
        $responseB = $this->postJson("/api/v1/payments/start/{$orderB->id}")->assertCreated();

        $refA = $responseA->json('data.merchant_reference');
        $refB = $responseB->json('data.merchant_reference');

        $this->assertNotSame($refA, $refB);
        $this->assertDatabaseCount('payment_transactions', 2);
    }

    public function test_provider_resolution_via_di(): void
    {
        /** @var PaymentOrchestrator $orchestrator */
        $orchestrator = app(PaymentOrchestrator::class);

        $this->assertContains('nmb', $orchestrator->registeredProviders());
        $this->assertInstanceOf(NmbPaymentProvider::class, $orchestrator->resolveProvider('nmb'));
    }

    public function test_order_has_payment_transactions_relationship(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'status' => OrderStatus::PendingPayment,
            'total' => 10000,
        ]);

        PaymentTransaction::factory()->processing()->create([
            'order_id' => $order->id,
            'provider' => PaymentProvider::Nmb,
            'amount' => 10000,
        ]);

        $this->assertSame(1, $order->paymentTransactions()->count());
        $this->assertTrue($order->paymentTransactions()->first()->order->is($order));
    }

    public function test_ownership_enforced(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $order = $this->createPayableOrder($owner, [
            'total' => 10000,
        ]);

        Sanctum::actingAs($owner);
        $transactionId = $this->postJson("/api/v1/payments/start/{$order->id}")->json('data.id');

        Sanctum::actingAs($other);
        $this->getJson("/api/v1/payments/{$transactionId}")->assertNotFound();
        $this->postJson("/api/v1/payments/start/{$order->id}")->assertNotFound();
    }

    public function test_guest_and_admin_rejected(): void
    {
        $order = Order::factory()->create([
            'status' => OrderStatus::PendingPayment,
            'total' => 10000,
        ]);

        $this->postJson("/api/v1/payments/start/{$order->id}")->assertUnauthorized();

        Sanctum::actingAs(Admin::factory()->create());
        $this->postJson("/api/v1/payments/start/{$order->id}")->assertUnauthorized();
    }

    public function test_nmb_adapter_reads_config_not_hardcoded_credentials(): void
    {
        config([
            'services.nmb.base_url' => 'https://nmb.example.test',
            'services.nmb.username' => 'configured-user',
            'services.nmb.password' => 'configured-pass',
            'services.nmb.merchant_id' => 'merchant-1',
            'payments.nmb.base_url' => 'https://nmb.example.test',
            'payments.nmb.username' => 'configured-user',
            'payments.nmb.password' => 'configured-pass',
            'payments.nmb.merchant_id' => 'merchant-1',
            'payments.nmb.enabled' => true,
            'payments.nmb.environment' => 'sandbox',
        ]);

        Http::fake([
            'nmb.example.test/*' => Http::response([
                'result' => 'SUCCESS',
                'session' => [
                    'id' => 'SESSION-CFG',
                    'successIndicator' => 'ind-cfg',
                ],
            ]),
        ]);

        $user = User::factory()->create();
        $order = $this->createPayableOrder($user, [
            'total' => 10000,
        ]);

        Sanctum::actingAs($user);
        $payload = $this->postJson("/api/v1/payments/start/{$order->id}")->assertCreated()->json('data');

        $this->assertStringContainsString('https://nmb.example.test', $payload['request_payload']['endpoint']);
        $this->assertTrue($payload['request_payload']['username_configured']);
        $this->assertTrue($payload['request_payload']['password_configured']);
        $this->assertSame('merchant-1', $payload['request_payload']['merchant_id']);
        $this->assertStringNotContainsString('configured-pass', (string) json_encode($payload));
    }
}
