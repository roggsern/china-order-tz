<?php

namespace Tests\Feature\Payments;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentProvider;
use App\Enums\PaymentTransactionStatus;
use App\Models\Order;
use App\Models\PaymentTransaction;
use App\Models\User;
use App\Services\Settings\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PayNowPaymentRecoveryTest extends TestCase
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
            'payments.nmb.enabled' => true,
            'payments.nmb.base_url' => 'https://sandbox.nmb.test',
            'payments.nmb.merchant_id' => 'TESTMERCHANT',
            'payments.nmb.password' => 'sandbox-password',
            'payments.orchestrator.default_provider' => 'nmb',
            'payments.snippe.enabled' => true,
            'payments.snippe.base_url' => 'https://api.snippe.test',
            'payments.snippe.api_key' => 'snippe-test-api-key',
            'payments.snippe.webhook_secret' => 'whsec_test_placeholder',
            'payments.snippe.webhook_url' => 'https://example.test/api/v1/payments/snippe/webhook',
            'payments.snippe.http_timeout' => 5,
            'payments.snippe.http_connect_timeout' => 2,
            'payments.snippe.http_retry_times' => 0,
        ]);

        app(SettingsService::class)->set('payments.enabled_methods', [
            'nmb' => true,
            'snippe' => true,
            'mpesa' => false,
            'card' => false,
            'cash' => false,
            'bank_transfer' => false,
        ]);
        Cache::flush();
    }

    public function test_pay_now_refreshes_active_snippe_before_rejecting_a_different_provider(): void
    {
        $user = User::factory()->create();
        $order = $this->createPayableOrder($user, ['total' => 45000]);
        $transaction = $this->createActiveSnippeTransaction($order, 'pi_still_pending');

        $snippeGets = 0;
        $nmbSessions = 0;
        $snippePosts = 0;

        Http::fake(function (\Illuminate\Http\Client\Request $request) use (&$snippeGets, &$nmbSessions, &$snippePosts) {
            $url = $request->url();

            if ($request->method() === 'GET' && str_contains($url, 'api.snippe.test/v1/payments/pi_still_pending')) {
                $snippeGets++;

                return Http::response($this->snippeStatusPayload('pi_still_pending', 'pending'));
            }

            if ($request->method() === 'POST' && rtrim($url, '/') === 'https://api.snippe.test/v1/payments') {
                $snippePosts++;

                return Http::response(['status' => 'success', 'code' => 201, 'data' => ['reference' => 'pi_new', 'status' => 'pending']], 201);
            }

            if (str_contains($url, '/session')) {
                $nmbSessions++;

                return Http::response([
                    'result' => 'SUCCESS',
                    'session' => [
                        'id' => 'SESSION-SHOULD-NOT-CREATE',
                        'successIndicator' => 'ind',
                        'checkoutUrl' => 'https://checkout.nmb.test/pay/blocked',
                    ],
                ]);
            }

            return Http::response(['result' => 'PENDING'], 200);
        });

        Sanctum::actingAs($user);

        $this->postJson("/api/v1/payments/start/{$order->id}", [
            'provider' => PaymentProvider::Nmb->value,
        ])
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('code', 'payment_in_progress')
            ->assertJsonPath('payment_transaction_id', $transaction->id)
            ->assertJsonPath('payment_transaction_status', PaymentTransactionStatus::Processing->value)
            ->assertJsonPath('provider', PaymentProvider::Snippe->value)
            ->assertJsonPath('message', 'An active payment is already in progress for this order.');

        $this->assertSame(1, $snippeGets);
        $this->assertSame(0, $nmbSessions);
        $this->assertSame(0, $snippePosts);
        $this->assertDatabaseCount('payment_transactions', 1);
        $this->assertSame(OrderStatus::PendingPayment, $order->fresh()->status);
    }

    public function test_same_provider_reuse_does_not_create_a_second_snippe_collection(): void
    {
        $user = User::factory()->create();
        $order = $this->createPayableOrder($user, ['total' => 45000]);
        $transaction = $this->createActiveSnippeTransaction($order, 'pi_reuse');

        $snippePosts = 0;

        Http::fake(function (\Illuminate\Http\Client\Request $request) use (&$snippePosts) {
            $url = $request->url();

            if ($request->method() === 'GET' && str_contains($url, 'api.snippe.test/v1/payments/pi_reuse')) {
                return Http::response($this->snippeStatusPayload('pi_reuse', 'pending'));
            }

            if ($request->method() === 'POST' && rtrim($url, '/') === 'https://api.snippe.test/v1/payments') {
                $snippePosts++;

                return Http::response(['status' => 'success', 'code' => 201, 'data' => ['reference' => 'pi_second', 'status' => 'pending']], 201);
            }

            return Http::response(['result' => 'PENDING'], 200);
        });

        Sanctum::actingAs($user);

        $this->postJson("/api/v1/payments/start/{$order->id}", [
            'provider' => PaymentMethod::Snippe->value,
            'phone_number' => '0712345678',
        ])
            ->assertCreated()
            ->assertJsonPath('data.id', $transaction->id)
            ->assertJsonPath('data.status', PaymentTransactionStatus::Processing->value);

        $this->assertSame(0, $snippePosts);
        $this->assertDatabaseCount('payment_transactions', 1);
    }

    public function test_active_successful_refresh_does_not_start_another_payment(): void
    {
        $user = User::factory()->create();
        $order = $this->createPayableOrder($user, ['total' => 45000]);
        $transaction = $this->createActiveSnippeTransaction($order, 'pi_paid');

        Http::fake([
            'api.snippe.test/v1/payments/pi_paid' => Http::response($this->snippeStatusPayload('pi_paid', 'completed', 45000)),
            'sandbox.nmb.test/*' => Http::response(['result' => 'SUCCESS', 'session' => ['id' => 'SESSION-DUP', 'successIndicator' => 'x']]),
        ]);

        Sanctum::actingAs($user);

        $this->postJson("/api/v1/payments/start/{$order->id}", [
            'provider' => PaymentProvider::Nmb->value,
        ])
            ->assertCreated()
            ->assertJsonPath('data.id', $transaction->id)
            ->assertJsonPath('data.status', PaymentTransactionStatus::Successful->value);

        $this->assertDatabaseCount('payment_transactions', 1);
        $this->assertSame(OrderStatus::Paid, $order->fresh()->status);
        $this->assertNotNull($order->fresh()->paid_at);
    }

    public function test_failed_active_attempt_allows_a_new_method(): void
    {
        $this->assertReplacementAllowedAfterProviderStatus('failed');
    }

    public function test_expired_active_attempt_allows_a_new_method(): void
    {
        $this->assertReplacementAllowedAfterProviderStatus('expired');
    }

    public function test_cancelled_active_attempt_allows_a_new_method(): void
    {
        $this->assertReplacementAllowedAfterProviderStatus('voided', PaymentTransactionStatus::Cancelled);
    }

    public function test_old_unpaid_order_still_exposes_can_pay(): void
    {
        $user = User::factory()->create();
        $order = $this->createPayableOrder($user, [
            'total' => 45000,
            'created_at' => now()->subDays(10),
        ]);

        Sanctum::actingAs($user);

        $this->getJson("/api/v1/orders/{$order->id}")
            ->assertOk()
            ->assertJsonPath('data.can_pay', true)
            ->assertJsonPath('data.active_payment_transaction', null)
            ->assertJsonPath('data.status', OrderStatus::PendingPayment->value);

        $this->getJson('/api/v1/orders')
            ->assertOk()
            ->assertJsonPath('data.0.can_pay', true)
            ->assertJsonPath('data.0.active_payment_transaction', null);
    }

    public function test_processing_transaction_is_discovered_on_list_and_detail(): void
    {
        $user = User::factory()->create();
        $order = $this->createPayableOrder($user, ['total' => 45000]);
        $transaction = $this->createActiveSnippeTransaction($order, 'pi_visible');

        Sanctum::actingAs($user);

        $this->getJson("/api/v1/orders/{$order->id}")
            ->assertOk()
            ->assertJsonPath('data.can_pay', true)
            ->assertJsonPath('data.active_payment_transaction.id', $transaction->id)
            ->assertJsonPath('data.active_payment_transaction.status', PaymentTransactionStatus::Processing->value)
            ->assertJsonPath('data.active_payment_transaction.provider', PaymentProvider::Snippe->value)
            ->assertJsonPath('data.payment.payment_transaction_id', $transaction->id);

        $this->getJson('/api/v1/orders')
            ->assertOk()
            ->assertJsonPath('data.0.can_pay', true)
            ->assertJsonPath('data.0.active_payment_transaction.id', $transaction->id);
    }

    public function test_paid_and_cancelled_orders_are_not_payable(): void
    {
        $user = User::factory()->create();

        $paid = Order::factory()->create([
            'user_id' => $user->id,
            'status' => OrderStatus::Paid,
            'paid_at' => now(),
            'total' => 12000,
        ]);
        $cancelled = Order::factory()->create([
            'user_id' => $user->id,
            'status' => OrderStatus::Cancelled,
            'total' => 12000,
        ]);
        $refunded = Order::factory()->create([
            'user_id' => $user->id,
            'status' => OrderStatus::Refunded,
            'total' => 12000,
        ]);

        Sanctum::actingAs($user);

        $this->getJson("/api/v1/orders/{$paid->id}")->assertOk()->assertJsonPath('data.can_pay', false);
        $this->getJson("/api/v1/orders/{$cancelled->id}")->assertOk()->assertJsonPath('data.can_pay', false);
        $this->getJson("/api/v1/orders/{$refunded->id}")->assertOk()->assertJsonPath('data.can_pay', false);
    }

    public function test_cash_remains_unselectable_unless_configured(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $payload = $this->getJson('/api/v1/payments/methods')
            ->assertOk()
            ->json('data');

        $cash = collect($payload['methods'])->firstWhere('code', 'cash');

        $this->assertNotNull($cash);
        $this->assertFalse($cash['enabled']);
        $this->assertFalse($cash['selectable']);
    }

    /**
     * @param  'failed'|'expired'|'voided'  $providerStatus
     */
    private function assertReplacementAllowedAfterProviderStatus(
        string $providerStatus,
        PaymentTransactionStatus $expectedTerminal = PaymentTransactionStatus::Failed,
    ): void {
        $user = User::factory()->create();
        $order = $this->createPayableOrder($user, ['total' => 45000]);
        $transaction = $this->createActiveSnippeTransaction($order, 'pi_replace_'.$providerStatus);

        Http::fake([
            'api.snippe.test/v1/payments/pi_replace_'.$providerStatus => Http::response(
                $this->snippeStatusPayload('pi_replace_'.$providerStatus, $providerStatus),
            ),
            'sandbox.nmb.test/*/session' => Http::response([
                'result' => 'SUCCESS',
                'session' => [
                    'id' => 'SESSION-AFTER-'.$providerStatus,
                    'successIndicator' => 'ind-after',
                    'checkoutUrl' => 'https://checkout.nmb.test/pay/after',
                ],
            ]),
            'sandbox.nmb.test/*/order/*' => Http::response([
                'result' => 'PENDING',
                'order' => ['id' => 'pending', 'amount' => '0.00', 'currency' => 'TZS'],
            ]),
        ]);

        Sanctum::actingAs($user);

        $this->postJson("/api/v1/payments/start/{$order->id}", [
            'provider' => PaymentProvider::Nmb->value,
        ])
            ->assertCreated()
            ->assertJsonPath('data.provider', PaymentProvider::Nmb->value)
            ->assertJsonPath('data.status', PaymentTransactionStatus::Processing->value);

        $this->assertDatabaseCount('payment_transactions', 2);
        $this->assertSame($expectedTerminal, $transaction->fresh()->status);
        $this->assertSame(OrderStatus::PendingPayment, $order->fresh()->status);
        $this->assertNull($order->fresh()->paid_at);
    }

    private function createActiveSnippeTransaction(Order $order, string $reference): PaymentTransaction
    {
        return PaymentTransaction::factory()->processing()->create([
            'order_id' => $order->id,
            'provider' => PaymentProvider::Snippe,
            'provider_reference' => $reference,
            'merchant_reference' => 'COTZ-PAY-SNIPPE-'.$reference,
            'amount' => 45000,
            'currency' => 'TZS',
            'status' => PaymentTransactionStatus::Processing,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function snippeStatusPayload(string $reference, string $status, int $amount = 45000): array
    {
        $data = [
            'reference' => $reference,
            'status' => $status,
        ];

        if ($status === 'completed') {
            $data['amount'] = ['value' => $amount, 'currency' => 'TZS'];
        }

        return [
            'status' => 'success',
            'code' => 200,
            'data' => $data,
        ];
    }
}
