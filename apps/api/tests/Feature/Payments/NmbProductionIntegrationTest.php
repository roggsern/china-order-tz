<?php

namespace Tests\Feature\Payments;

use App\Enums\OrderStatus;
use App\Enums\PaymentTransactionStatus;
use App\Models\Order;
use App\Models\PaymentTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class NmbProductionIntegrationTest extends TestCase
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
    }

    /**
     * @param  array<string, mixed>  $session
     * @param  callable(string): array<string, mixed>|null  $orderResponder
     */
    private function fakeNmbApi(array $session, ?callable $orderResponder = null): void
    {
        Http::fake(function (Request $request) use ($session, $orderResponder) {
            $url = $request->url();

            if (str_contains($url, '/session')) {
                return Http::response([
                    'result' => 'SUCCESS',
                    'session' => $session,
                ]);
            }

            if (str_contains($url, '/order/')) {
                $orderId = (string) str($url)->afterLast('/order/')->before('?')->toString();

                if ($orderResponder !== null) {
                    return Http::response($orderResponder($orderId));
                }

                return Http::response([
                    'result' => 'PENDING',
                    'order' => [
                        'id' => $orderId,
                        'amount' => '0.00',
                        'currency' => 'TZS',
                    ],
                ]);
            }

            return Http::response(['result' => 'ERROR'], 500);
        });
    }

    public function test_production_config_is_loaded_for_initiation(): void
    {
        $this->fakeNmbApi([
            'id' => 'SESSION-CFG-1',
            'successIndicator' => 'ind-1',
            'checkoutUrl' => 'https://checkout.nmb.test/pay/1',
        ]);

        $user = User::factory()->create();
        $order = $this->createPayableOrder($user, [
            'total' => 25000,
            'currency' => 'TZS',
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson("/api/v1/payments/start/{$order->id}")->assertCreated();

        $this->assertStringContainsString(
            'sandbox.nmb.test',
            (string) data_get($response->json('data.request_payload'), 'endpoint'),
        );
        $this->assertSame('TESTMERCHANT', data_get($response->json('data.request_payload'), 'merchant_id'));
        $this->assertStringNotContainsString('sandbox-password', (string) json_encode($response->json('data')));
    }

    public function test_payment_initiation_creates_processing_transaction(): void
    {
        $this->fakeNmbApi([
            'id' => 'SESSION-INIT-1',
            'successIndicator' => 'indicator-init',
            'checkoutUrl' => 'https://checkout.nmb.test/pay/init',
        ]);

        $user = User::factory()->create();
        $order = $this->createPayableOrder($user, [
            'total' => 30000,
            'currency' => 'TZS',
        ]);

        Sanctum::actingAs($user);

        $this->postJson("/api/v1/payments/start/{$order->id}")
            ->assertCreated()
            ->assertJsonPath('data.status', 'processing')
            ->assertJsonPath('data.provider_reference', 'SESSION-INIT-1')
            ->assertJsonPath('data.checkout_url', 'https://checkout.nmb.test/pay/init')
            ->assertJsonPath('data.success_indicator', 'indicator-init');

        $this->assertSame(OrderStatus::PendingPayment, $order->fresh()->status);
        $this->assertNull($order->fresh()->paid_at);
    }

    public function test_successful_callback_marks_transaction_and_order_paid(): void
    {
        $this->fakeNmbApi(
            [
                'id' => 'SESSION-OK-1',
                'successIndicator' => 'ind-ok',
            ],
            fn (string $orderId): array => [
                'result' => 'SUCCESS',
                'order' => [
                    'id' => $orderId,
                    'amount' => number_format(
                        (float) PaymentTransaction::query()
                            ->where('merchant_reference', $orderId)
                            ->value('amount'),
                        2,
                        '.',
                        '',
                    ),
                    'currency' => 'TZS',
                ],
                'transaction' => [
                    'id' => 'TXN-OK-1',
                ],
            ],
        );

        $user = User::factory()->create();
        $order = $this->createPayableOrder($user, [
            'total' => 40000,
            'currency' => 'TZS',
        ]);

        Sanctum::actingAs($user);
        $start = $this->postJson("/api/v1/payments/start/{$order->id}")->assertCreated();
        $transactionId = $start->json('data.id');
        $merchantReference = $start->json('data.merchant_reference');

        $this->postJson('/api/v1/payments/nmb/callback', [
            'result' => 'SUCCESS',
            'session' => ['id' => 'SESSION-OK-1'],
            'order' => ['id' => $merchantReference],
            'transaction' => ['id' => 'TXN-OK-1'],
        ])->assertOk()
            ->assertJsonPath('accepted', true)
            ->assertJsonPath('transaction_id', $transactionId);

        $transaction = PaymentTransaction::query()->findOrFail($transactionId);
        $this->assertSame(PaymentTransactionStatus::Successful, $transaction->status);
        $this->assertNotNull($transaction->completed_at);
        $this->assertNotNull($transaction->callback_received_at);
        $this->assertSame('TXN-OK-1', $transaction->external_transaction_id);

        $order->refresh();
        $this->assertSame(OrderStatus::Paid, $order->status);
        $this->assertNotNull($order->paid_at);
    }

    public function test_failed_callback_marks_transaction_failed_without_paying_order(): void
    {
        $this->fakeNmbApi([
            'id' => 'SESSION-FAIL-1',
            'successIndicator' => 'ind-fail',
        ]);

        $user = User::factory()->create();
        $order = $this->createPayableOrder($user, [
            'total' => 15000,
            'currency' => 'TZS',
        ]);

        Sanctum::actingAs($user);
        $transactionId = $this->postJson("/api/v1/payments/start/{$order->id}")->json('data.id');
        $merchantReference = PaymentTransaction::query()->findOrFail($transactionId)->merchant_reference;

        $this->postJson('/api/v1/payments/nmb/callback', [
            'result' => 'FAILURE',
            'session' => ['id' => 'SESSION-FAIL-1'],
            'order' => ['id' => $merchantReference],
        ])->assertOk()->assertJsonPath('accepted', true);

        $transaction = PaymentTransaction::query()->findOrFail($transactionId);
        $this->assertSame(PaymentTransactionStatus::Failed, $transaction->status);
        $this->assertSame(OrderStatus::PendingPayment, $order->fresh()->status);
        $this->assertNull($order->fresh()->paid_at);
    }

    public function test_duplicate_callback_is_idempotent(): void
    {
        $this->fakeNmbApi(
            [
                'id' => 'SESSION-DUP-1',
                'successIndicator' => 'ind-dup',
            ],
            fn (string $orderId): array => [
                'result' => 'SUCCESS',
                'order' => [
                    'id' => $orderId,
                    'amount' => number_format(
                        (float) PaymentTransaction::query()
                            ->where('merchant_reference', $orderId)
                            ->value('amount'),
                        2,
                        '.',
                        '',
                    ),
                    'currency' => 'TZS',
                ],
                'transaction' => ['id' => 'TXN-DUP-1'],
            ],
        );

        $user = User::factory()->create();
        $order = $this->createPayableOrder($user, [
            'total' => 22000,
            'currency' => 'TZS',
        ]);

        Sanctum::actingAs($user);
        $start = $this->postJson("/api/v1/payments/start/{$order->id}")->assertCreated();
        $transactionId = $start->json('data.id');
        $merchantReference = $start->json('data.merchant_reference');

        $payload = [
            'result' => 'SUCCESS',
            'session' => ['id' => 'SESSION-DUP-1'],
            'order' => ['id' => $merchantReference],
            'transaction' => ['id' => 'TXN-DUP-1'],
        ];

        $this->postJson('/api/v1/payments/nmb/callback', $payload)->assertOk();
        $paidAt = $order->fresh()->paid_at;

        $this->postJson('/api/v1/payments/nmb/callback', $payload)
            ->assertOk()
            ->assertJsonPath('message', 'NMB callback already processed.');

        $this->assertSame(1, PaymentTransaction::query()->where('status', 'successful')->count());
        $this->assertEquals($paidAt?->toIso8601String(), $order->fresh()->paid_at?->toIso8601String());
        $this->assertSame(OrderStatus::Paid, $order->fresh()->status);
        $this->assertSame($transactionId, PaymentTransaction::query()->where('status', 'successful')->value('id'));
    }

    public function test_invalid_callback_is_rejected(): void
    {
        $this->postJson('/api/v1/payments/nmb/callback', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['callback']);
    }

    public function test_refresh_can_mark_order_paid_after_verification(): void
    {
        $this->fakeNmbApi(
            [
                'id' => 'SESSION-REF-1',
                'successIndicator' => 'ind-ref',
            ],
            fn (string $orderId): array => [
                'result' => 'SUCCESS',
                'order' => [
                    'id' => $orderId,
                    'amount' => number_format(
                        (float) PaymentTransaction::query()
                            ->where('merchant_reference', $orderId)
                            ->value('amount'),
                        2,
                        '.',
                        '',
                    ),
                    'currency' => 'TZS',
                ],
                'transaction' => ['id' => 'TXN-REF-1'],
            ],
        );

        $user = User::factory()->create();
        $order = $this->createPayableOrder($user, [
            'total' => 18000,
            'currency' => 'TZS',
        ]);

        Sanctum::actingAs($user);
        $start = $this->postJson("/api/v1/payments/start/{$order->id}")->assertCreated();
        $transactionId = $start->json('data.id');

        $this->postJson("/api/v1/payments/{$transactionId}/refresh")
            ->assertOk()
            ->assertJsonPath('data.status', 'successful');

        $this->assertSame(OrderStatus::Paid, $order->fresh()->status);
        $this->assertNotNull($order->fresh()->paid_at);
    }
}
