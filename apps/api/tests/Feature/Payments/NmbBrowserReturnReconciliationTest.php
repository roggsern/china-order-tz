<?php

namespace Tests\Feature\Payments;

use App\Enums\OrderStatus;
use App\Enums\PaymentProvider;
use App\Enums\PaymentTransactionStatus;
use App\Models\PaymentTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class NmbBrowserReturnReconciliationTest extends TestCase
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

    public function test_unauthenticated_return_reconcile_marks_paid_after_verified_capture(): void
    {
        $user = User::factory()->create();
        $order = $this->createPayableOrder($user, ['total' => 3000, 'currency' => 'TZS']);

        Http::fake(function ($request) {
            if (str_contains($request->url(), '/session')) {
                return Http::response([
                    'result' => 'SUCCESS',
                    'session' => [
                        'id' => 'SESSION-RETURN-1',
                        'successIndicator' => 'ind-return-ok',
                    ],
                ]);
            }

            if (str_contains($request->url(), '/order/')) {
                $merchantRef = (string) str($request->url())->afterLast('/order/')->before('?');

                return Http::response([
                    'result' => 'SUCCESS',
                    'response' => ['gatewayCode' => 'APPROVED'],
                    'order' => [
                        'id' => $merchantRef,
                        'amount' => '3000.00',
                        'currency' => 'TZS',
                        'status' => 'CAPTURED',
                        'authenticationStatus' => 'AUTHENTICATION_SUCCESSFUL',
                        'totalAuthorizedAmount' => '3000.00',
                        'totalCapturedAmount' => '3000.00',
                    ],
                    'transaction' => [
                        [
                            'transaction' => [
                                'type' => 'AUTHENTICATION',
                                'result' => 'SUCCESS',
                            ],
                        ],
                        [
                            'transaction' => [
                                'id' => 'TXN-RETURN-PAY',
                                'type' => 'PAYMENT',
                                'result' => 'SUCCESS',
                            ],
                        ],
                    ],
                ]);
            }

            return Http::response(['result' => 'ERROR'], 500);
        });

        Sanctum::actingAs($user);
        $start = $this->postJson("/api/v1/payments/start/{$order->id}")->assertCreated();
        $transactionId = (string) $start->json('data.id');
        $merchantReference = (string) $start->json('data.merchant_reference');
        $successIndicator = (string) $start->json('data.success_indicator');
        $this->app['auth']->forgetGuards();

        $this->postJson('/api/v1/payments/nmb/return-reconcile', [
            'payment_transaction_id' => $transactionId,
            'merchant_reference' => $merchantReference,
            'success_indicator' => $successIndicator,
            'result_indicator' => $successIndicator,
            'order_id' => $order->id,
        ])
            ->assertOk()
            ->assertJsonPath('data.id', $transactionId)
            ->assertJsonPath('data.status', PaymentTransactionStatus::Successful->value);

        $this->assertSame(OrderStatus::Paid, $order->fresh()->status);
        $this->assertNotNull($order->fresh()->paid_at);
        $this->assertSame(
            PaymentTransactionStatus::Successful,
            PaymentTransaction::query()->findOrFail($transactionId)->status,
        );
    }

    public function test_invalid_return_proof_cannot_access_another_customers_payment(): void
    {
        $owner = User::factory()->create();
        $attackerOrder = $this->createPayableOrder(User::factory()->create(), [
            'total' => 5000,
            'currency' => 'TZS',
        ]);

        $transaction = PaymentTransaction::factory()->processing()->create([
            'order_id' => $this->createPayableOrder($owner, ['total' => 5000])->id,
            'provider' => PaymentProvider::Nmb,
            'merchant_reference' => 'COTZ-PAY-20260807-000100',
            'success_indicator' => 'secret-indicator-owner',
            'amount' => 5000,
            'currency' => 'TZS',
        ]);

        Http::fake();

        $this->postJson('/api/v1/payments/nmb/return-reconcile', [
            'payment_transaction_id' => $transaction->id,
            'merchant_reference' => $transaction->merchant_reference,
            'success_indicator' => 'wrong-indicator',
            'result_indicator' => 'wrong-indicator',
            'order_id' => $attackerOrder->id,
        ])->assertNotFound();

        $this->postJson('/api/v1/payments/nmb/return-reconcile', [
            'payment_transaction_id' => $transaction->id,
            'merchant_reference' => 'COTZ-PAY-20260807-999999',
            'success_indicator' => 'secret-indicator-owner',
            'result_indicator' => 'secret-indicator-owner',
        ])->assertNotFound();

        $this->assertSame(PaymentTransactionStatus::Processing, $transaction->fresh()->status);
        $this->assertSame(OrderStatus::PendingPayment, $transaction->order->fresh()->status);
    }

    public function test_pending_nmb_return_reconcile_keeps_processing_and_order_unpaid(): void
    {
        $user = User::factory()->create();
        $order = $this->createPayableOrder($user, ['total' => 32000, 'currency' => 'TZS']);

        Http::fake(function ($request) {
            if (str_contains($request->url(), '/session')) {
                return Http::response([
                    'result' => 'SUCCESS',
                    'session' => [
                        'id' => 'SESSION-PEND-RET',
                        'successIndicator' => 'ind-pend-ret',
                    ],
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
                    'transaction' => ['id' => 'TXN-PEND-RET'],
                ]);
            }

            return Http::response(['result' => 'ERROR'], 500);
        });

        Sanctum::actingAs($user);
        $start = $this->postJson("/api/v1/payments/start/{$order->id}")->assertCreated();
        $transactionId = (string) $start->json('data.id');
        $merchantReference = (string) $start->json('data.merchant_reference');
        $successIndicator = (string) $start->json('data.success_indicator');
        $this->app['auth']->forgetGuards();

        $this->postJson('/api/v1/payments/nmb/return-reconcile', [
            'payment_transaction_id' => $transactionId,
            'merchant_reference' => $merchantReference,
            'success_indicator' => $successIndicator,
            'result_indicator' => $successIndicator,
            'order_id' => $order->id,
        ])
            ->assertOk()
            ->assertJsonPath('data.status', PaymentTransactionStatus::Processing->value);

        $this->assertSame(OrderStatus::PendingPayment, $order->fresh()->status);
        $this->assertNull($order->fresh()->paid_at);
    }

    public function test_failed_nmb_return_reconcile_marks_failed_without_paying_order(): void
    {
        $user = User::factory()->create();
        $order = $this->createPayableOrder($user, ['total' => 9000, 'currency' => 'TZS']);

        Http::fake(function ($request) {
            if (str_contains($request->url(), '/session')) {
                return Http::response([
                    'result' => 'SUCCESS',
                    'session' => [
                        'id' => 'SESSION-FAIL-RET',
                        'successIndicator' => 'ind-fail-ret',
                    ],
                ]);
            }

            if (str_contains($request->url(), '/order/')) {
                $merchantRef = (string) str($request->url())->afterLast('/order/')->before('?');

                return Http::response([
                    'result' => 'SUCCESS',
                    'response' => ['gatewayCode' => 'DECLINED'],
                    'order' => [
                        'id' => $merchantRef,
                        'amount' => '9000.00',
                        'currency' => 'TZS',
                        'status' => 'FAILED',
                        'totalAuthorizedAmount' => 0,
                        'totalCapturedAmount' => 0,
                    ],
                    'transaction' => [
                        'id' => 'TXN-FAIL-RET',
                        'type' => 'PAYMENT',
                        'result' => 'FAILURE',
                    ],
                ]);
            }

            return Http::response(['result' => 'ERROR'], 500);
        });

        Sanctum::actingAs($user);
        $start = $this->postJson("/api/v1/payments/start/{$order->id}")->assertCreated();
        $transactionId = (string) $start->json('data.id');
        $merchantReference = (string) $start->json('data.merchant_reference');
        $successIndicator = (string) $start->json('data.success_indicator');
        $this->app['auth']->forgetGuards();

        $this->postJson('/api/v1/payments/nmb/return-reconcile', [
            'payment_transaction_id' => $transactionId,
            'merchant_reference' => $merchantReference,
            'success_indicator' => $successIndicator,
            'result_indicator' => $successIndicator,
        ])
            ->assertOk()
            ->assertJsonPath('data.status', PaymentTransactionStatus::Failed->value);

        $this->assertSame(OrderStatus::PendingPayment, $order->fresh()->status);
        $this->assertNull($order->fresh()->paid_at);
    }

    public function test_authenticated_refresh_still_requires_ownership(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $order = $this->createPayableOrder($owner, ['total' => 10000, 'currency' => 'TZS']);

        $transaction = PaymentTransaction::factory()->processing()->create([
            'order_id' => $order->id,
            'provider' => PaymentProvider::Nmb,
            'merchant_reference' => 'COTZ-PAY-20260807-000200',
            'success_indicator' => 'ind-auth-refresh',
            'amount' => 10000,
            'currency' => 'TZS',
        ]);

        Sanctum::actingAs($other);

        $this->postJson("/api/v1/payments/{$transaction->id}/refresh")
            ->assertNotFound();
    }
}
