<?php

namespace Tests\Feature\Payments;

use App\Enums\OrderStatus;
use App\Enums\PaymentProvider;
use App\Enums\PaymentTransactionStatus;
use App\Models\PaymentTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class NmbPaymentOutcomeIntegrityTest extends TestCase
{
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

    public function test_pending_authentication_payload_keeps_processing_and_order_unpaid(): void
    {
        $user = User::factory()->create();
        $order = $this->createPayableOrder($user, ['total' => 32000, 'currency' => 'TZS']);
        Sanctum::actingAs($user);

        Http::fake(function ($request) {
            if (str_contains($request->url(), '/session')) {
                return Http::response([
                    'result' => 'SUCCESS',
                    'session' => ['id' => 'SESSION-PEND', 'successIndicator' => 'ind-pend'],
                ]);
            }

            if (str_contains($request->url(), '/order/')) {
                $merchantRef = (string) str($request->url())->afterLast('/order/')->before('?');

                return Http::response([
                    'result' => 'SUCCESS',
                    'response' => ['gatewayCode' => 'PENDING'],
                    'order' => [
                        'id' => $merchantRef,
                        'amount' => '32000.00',
                        'currency' => 'TZS',
                        'status' => 'AUTHENTICATION_INITIATED',
                        'authenticationStatus' => 'AUTHENTICATION_PENDING',
                        'totalAuthorizedAmount' => 0,
                        'totalCapturedAmount' => 0,
                    ],
                    'transaction' => ['id' => 'TXN-PEND'],
                ]);
            }

            return Http::response(['result' => 'ERROR'], 500);
        });

        $transactionId = $this->postJson("/api/v1/payments/start/{$order->id}")
            ->assertCreated()
            ->json('data.id');

        $this->postJson("/api/v1/payments/{$transactionId}/refresh")->assertOk()
            ->assertJsonPath('data.status', PaymentTransactionStatus::Processing->value);

        $transaction = PaymentTransaction::query()->findOrFail($transactionId);
        $this->assertSame(PaymentTransactionStatus::Processing, $transaction->status);
        $this->assertNull($transaction->completed_at);
        $this->assertSame(OrderStatus::PendingPayment, $order->fresh()->status);
        $this->assertNull($order->fresh()->paid_at);
    }

    public function test_captured_callback_marks_paid(): void
    {
        $user = User::factory()->create();
        $order = $this->createPayableOrder($user, ['total' => 40000, 'currency' => 'TZS']);
        Sanctum::actingAs($user);

        Http::fake(function ($request) {
            if (str_contains($request->url(), '/session')) {
                return Http::response([
                    'result' => 'SUCCESS',
                    'session' => ['id' => 'SESSION-OK', 'successIndicator' => 'ind-ok'],
                ]);
            }

            if (str_contains($request->url(), '/order/')) {
                $merchantRef = (string) str($request->url())->afterLast('/order/')->before('?');

                return Http::response([
                    'result' => 'SUCCESS',
                    'response' => ['gatewayCode' => 'APPROVED'],
                    'order' => [
                        'id' => $merchantRef,
                        'amount' => '40000.00',
                        'currency' => 'TZS',
                        'status' => 'CAPTURED',
                        'authenticationStatus' => 'AUTHENTICATION_SUCCESSFUL',
                        'totalAuthorizedAmount' => '40000.00',
                        'totalCapturedAmount' => '40000.00',
                    ],
                    'transaction' => [
                        'id' => 'TXN-OK',
                        'type' => 'PAYMENT',
                        'result' => 'SUCCESS',
                    ],
                ]);
            }

            return Http::response(['result' => 'ERROR'], 500);
        });

        $start = $this->postJson("/api/v1/payments/start/{$order->id}")->assertCreated();
        $transactionId = $start->json('data.id');
        $merchantReference = $start->json('data.merchant_reference');

        $this->postJson('/api/v1/payments/nmb/callback', [
            'result' => 'SUCCESS',
            'session' => ['id' => 'SESSION-OK'],
            'order' => ['id' => $merchantReference],
            'transaction' => ['id' => 'TXN-OK'],
        ])->assertOk();

        $transaction = PaymentTransaction::query()->findOrFail($transactionId);
        $this->assertSame(PaymentTransactionStatus::Successful, $transaction->status);
        $this->assertSame(OrderStatus::Paid, $order->fresh()->status);
        $this->assertNotNull($order->fresh()->paid_at);
    }

    public function test_declined_verification_marks_failed_without_paying(): void
    {
        $user = User::factory()->create();
        $order = $this->createPayableOrder($user, ['total' => 15000, 'currency' => 'TZS']);
        Sanctum::actingAs($user);

        Http::fake(function ($request) {
            if (str_contains($request->url(), '/session')) {
                return Http::response([
                    'result' => 'SUCCESS',
                    'session' => ['id' => 'SESSION-DEC', 'successIndicator' => 'ind-dec'],
                ]);
            }

            if (str_contains($request->url(), '/order/')) {
                $merchantRef = (string) str($request->url())->afterLast('/order/')->before('?');

                return Http::response([
                    'result' => 'SUCCESS',
                    'response' => ['gatewayCode' => 'DECLINED'],
                    'order' => [
                        'id' => $merchantRef,
                        'amount' => '15000.00',
                        'currency' => 'TZS',
                        'status' => 'FAILED',
                        'totalAuthorizedAmount' => 0,
                        'totalCapturedAmount' => 0,
                    ],
                ]);
            }

            return Http::response(['result' => 'ERROR'], 500);
        });

        $transactionId = $this->postJson("/api/v1/payments/start/{$order->id}")->json('data.id');
        $this->postJson("/api/v1/payments/{$transactionId}/refresh")->assertOk()
            ->assertJsonPath('data.status', PaymentTransactionStatus::Failed->value);

        $this->assertSame(OrderStatus::PendingPayment, $order->fresh()->status);
        $this->assertNull($order->fresh()->paid_at);
    }

    public function test_duplicate_callback_remains_idempotent_after_paid(): void
    {
        $user = User::factory()->create();
        $order = $this->createPayableOrder($user, ['total' => 22000, 'currency' => 'TZS']);
        Sanctum::actingAs($user);

        Http::fake(function ($request) {
            if (str_contains($request->url(), '/session')) {
                return Http::response([
                    'result' => 'SUCCESS',
                    'session' => ['id' => 'SESSION-DUP', 'successIndicator' => 'ind-dup'],
                ]);
            }

            if (str_contains($request->url(), '/order/')) {
                $merchantRef = (string) str($request->url())->afterLast('/order/')->before('?');

                return Http::response([
                    'result' => 'SUCCESS',
                    'response' => ['gatewayCode' => 'APPROVED'],
                    'order' => [
                        'id' => $merchantRef,
                        'amount' => '22000.00',
                        'currency' => 'TZS',
                        'status' => 'CAPTURED',
                        'totalAuthorizedAmount' => '22000.00',
                        'totalCapturedAmount' => '22000.00',
                    ],
                    'transaction' => ['id' => 'TXN-DUP', 'result' => 'SUCCESS'],
                ]);
            }

            return Http::response(['result' => 'ERROR'], 500);
        });

        $start = $this->postJson("/api/v1/payments/start/{$order->id}")->assertCreated();
        $transactionId = $start->json('data.id');
        $merchantReference = $start->json('data.merchant_reference');

        $payload = [
            'result' => 'SUCCESS',
            'session' => ['id' => 'SESSION-DUP'],
            'order' => ['id' => $merchantReference],
            'transaction' => ['id' => 'TXN-DUP'],
        ];

        $this->postJson('/api/v1/payments/nmb/callback', $payload)->assertOk();
        $paidAt = $order->fresh()->paid_at;
        $this->assertSame(OrderStatus::Paid, $order->fresh()->status);

        $this->postJson('/api/v1/payments/nmb/callback', $payload)->assertOk();
        $this->assertSame(OrderStatus::Paid, $order->fresh()->status);
        $this->assertEquals($paidAt?->toIso8601String(), $order->fresh()->paid_at?->toIso8601String());
        $this->assertSame(PaymentTransactionStatus::Successful, PaymentTransaction::query()->findOrFail($transactionId)->status);
    }

    public function test_paid_order_cannot_regress_via_pending_refresh(): void
    {
        $user = User::factory()->create();
        $order = $this->createPayableOrder($user, ['total' => 18000, 'currency' => 'TZS']);
        Sanctum::actingAs($user);

        $call = 0;
        Http::fake(function ($request) use (&$call) {
            if (str_contains($request->url(), '/session')) {
                return Http::response([
                    'result' => 'SUCCESS',
                    'session' => ['id' => 'SESSION-REG', 'successIndicator' => 'ind-reg'],
                ]);
            }

            if (str_contains($request->url(), '/order/')) {
                $merchantRef = (string) str($request->url())->afterLast('/order/')->before('?');
                $call++;

                if ($call === 1) {
                    return Http::response([
                        'result' => 'SUCCESS',
                        'response' => ['gatewayCode' => 'APPROVED'],
                        'order' => [
                            'id' => $merchantRef,
                            'amount' => '18000.00',
                            'currency' => 'TZS',
                            'status' => 'CAPTURED',
                            'totalAuthorizedAmount' => '18000.00',
                            'totalCapturedAmount' => '18000.00',
                        ],
                        'transaction' => ['id' => 'TXN-REG', 'result' => 'SUCCESS'],
                    ]);
                }

                return Http::response([
                    'result' => 'SUCCESS',
                    'response' => ['gatewayCode' => 'PENDING'],
                    'order' => [
                        'id' => $merchantRef,
                        'amount' => '18000.00',
                        'currency' => 'TZS',
                        'status' => 'AUTHENTICATION_INITIATED',
                        'authenticationStatus' => 'AUTHENTICATION_PENDING',
                        'totalAuthorizedAmount' => 0,
                        'totalCapturedAmount' => 0,
                    ],
                ]);
            }

            return Http::response(['result' => 'ERROR'], 500);
        });

        $transactionId = $this->postJson("/api/v1/payments/start/{$order->id}")->json('data.id');
        $this->postJson("/api/v1/payments/{$transactionId}/refresh")->assertOk()
            ->assertJsonPath('data.status', PaymentTransactionStatus::Successful->value);
        $this->assertSame(OrderStatus::Paid, $order->fresh()->status);

        // Second refresh with pending payload must not regress Successful/Paid.
        $this->postJson("/api/v1/payments/{$transactionId}/refresh")->assertOk()
            ->assertJsonPath('data.status', PaymentTransactionStatus::Successful->value);
        $this->assertSame(OrderStatus::Paid, $order->fresh()->status);
    }

    public function test_recovery_command_reverts_false_paid_without_deleting_history(): void
    {
        $user = User::factory()->create();
        $order = $this->createPayableOrder($user, ['total' => 12000, 'currency' => 'TZS']);
        $transaction = PaymentTransaction::factory()->create([
            'order_id' => $order->id,
            'provider' => PaymentProvider::Nmb,
            'status' => PaymentTransactionStatus::Successful,
            'amount' => 12000,
            'currency' => 'TZS',
            'merchant_reference' => 'COTZ-PAY-RECOVER-1',
            'provider_reference' => 'SESSION-BAD',
            'completed_at' => now(),
        ]);

        $order->update([
            'status' => OrderStatus::Paid,
            'paid_at' => now(),
        ]);

        Artisan::call('payments:revert-false-nmb-paid', [
            '--payment-transaction' => $transaction->id,
            '--force' => true,
            '--confirm' => 'REVERT_FALSE_PAID_NMB',
        ]);

        $transaction->refresh();
        $order->refresh();

        $this->assertSame(PaymentTransactionStatus::Processing, $transaction->status);
        $this->assertNull($transaction->completed_at);
        $this->assertSame(OrderStatus::PendingPayment, $order->status);
        $this->assertNull($order->paid_at);
        $this->assertDatabaseHas('order_status_history', [
            'order_id' => $order->id,
            'previous_status' => OrderStatus::Paid->value,
            'new_status' => OrderStatus::PendingPayment->value,
            'source' => 'payments:revert-false-nmb-paid',
        ]);

        $second = Artisan::call('payments:revert-false-nmb-paid', [
            '--payment-transaction' => $transaction->id,
            '--force' => true,
            '--confirm' => 'REVERT_FALSE_PAID_NMB',
        ]);
        $this->assertSame(1, $second);
    }
}
