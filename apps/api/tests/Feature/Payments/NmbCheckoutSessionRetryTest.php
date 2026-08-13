<?php

namespace Tests\Feature\Payments;

use App\Enums\ActivityEventType;
use App\Enums\OrderStatus;
use App\Enums\PaymentProvider;
use App\Enums\PaymentTransactionStatus;
use App\Models\ActivityLog;
use App\Models\Order;
use App\Models\PaymentTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class NmbCheckoutSessionRetryTest extends TestCase
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
            'payments.nmb.base_url' => 'https://sandbox.nmb.test',
            'payments.nmb.merchant_id' => 'TESTMERCHANT',
            'payments.nmb.password' => 'sandbox-password',
            'payments.orchestrator.default_provider' => 'nmb',
        ]);
    }

    public function test_retry_creates_different_session_id_for_pending_nmb_payment(): void
    {
        Http::fake([
            'sandbox.nmb.test/*/session' => Http::sequence()
                ->push([
                    'result' => 'SUCCESS',
                    'session' => [
                        'id' => 'SESSION-OLD',
                        'successIndicator' => 'indicator-old',
                    ],
                ])
                ->push([
                    'result' => 'SUCCESS',
                    'session' => [
                        'id' => 'SESSION-FRESH',
                        'successIndicator' => 'indicator-fresh',
                    ],
                ]),
        ]);

        $user = User::factory()->create();
        $order = $this->createPayableOrder($user, ['total' => 25000]);
        Sanctum::actingAs($user);

        $transactionId = $this->postJson("/api/v1/payments/start/{$order->id}")
            ->assertCreated()
            ->json('data.id');

        $this->assertSame('SESSION-OLD', PaymentTransaction::query()->findOrFail($transactionId)->provider_reference);

        $response = $this->postJson("/api/v1/payments/{$transactionId}/nmb/checkout-session")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $transactionId)
            ->assertJsonPath('data.provider_reference', 'SESSION-FRESH')
            ->assertJsonPath('data.success_indicator', 'indicator-fresh')
            ->assertJsonPath('data.status', PaymentTransactionStatus::Processing->value);

        $this->assertSame($order->id, $response->json('data.order_id'));
        $this->assertDatabaseCount('payment_transactions', 1);
        $this->assertSame('SESSION-FRESH', PaymentTransaction::query()->findOrFail($transactionId)->provider_reference);
        $this->assertSame(OrderStatus::PendingPayment, $order->fresh()->status);

        $this->assertTrue(
            ActivityLog::query()
                ->where('event_type', ActivityEventType::PaymentCheckoutSessionRefreshed->value)
                ->where('subject_id', $transactionId)
                ->exists()
        );
    }

    public function test_failed_nmb_payment_can_retry_session(): void
    {
        Http::fake([
            'sandbox.nmb.test/*/session' => Http::response([
                'result' => 'SUCCESS',
                'session' => [
                    'id' => 'SESSION-AFTER-FAIL',
                    'successIndicator' => 'ind-after-fail',
                ],
            ]),
        ]);

        $user = User::factory()->create();
        $order = $this->createPayableOrder($user, ['total' => 12000]);
        $transaction = PaymentTransaction::factory()->create([
            'order_id' => $order->id,
            'provider' => PaymentProvider::Nmb,
            'status' => PaymentTransactionStatus::Failed,
            'provider_reference' => 'SESSION-EXPIRED',
            'success_indicator' => 'ind-expired',
            'amount' => 12000,
            'currency' => 'TZS',
            'merchant_reference' => 'COTZ-PAY-20260805-000001',
        ]);

        Sanctum::actingAs($user);

        $this->postJson("/api/v1/payments/{$transaction->id}/nmb/checkout-session")
            ->assertOk()
            ->assertJsonPath('data.provider_reference', 'SESSION-AFTER-FAIL')
            ->assertJsonPath('data.status', PaymentTransactionStatus::Processing->value);
    }

    public function test_successful_transaction_cannot_retry_session(): void
    {
        $user = User::factory()->create();
        $order = $this->createPayableOrder($user, ['total' => 12000]);
        $transaction = PaymentTransaction::factory()->create([
            'order_id' => $order->id,
            'provider' => PaymentProvider::Nmb,
            'status' => PaymentTransactionStatus::Successful,
            'provider_reference' => 'SESSION-PAID',
            'amount' => 12000,
            'currency' => 'TZS',
            'merchant_reference' => 'COTZ-PAY-20260805-000002',
        ]);

        Sanctum::actingAs($user);

        $this->postJson("/api/v1/payments/{$transaction->id}/nmb/checkout-session")
            ->assertStatus(422)
            ->assertJsonPath('code', 'business_rule_violated')
            ->assertJsonValidationErrors(['payment']);
    }

    public function test_non_nmb_transaction_cannot_retry_session(): void
    {
        $user = User::factory()->create();
        $order = $this->createPayableOrder($user, ['total' => 12000]);
        $transaction = PaymentTransaction::factory()->create([
            'order_id' => $order->id,
            'provider' => PaymentProvider::Selcom,
            'status' => PaymentTransactionStatus::Processing,
            'provider_reference' => 'MOCK-1',
            'amount' => 12000,
            'currency' => 'TZS',
            'merchant_reference' => 'COTZ-PAY-20260805-000003',
        ]);

        Sanctum::actingAs($user);

        $this->postJson("/api/v1/payments/{$transaction->id}/nmb/checkout-session")
            ->assertStatus(422)
            ->assertJsonPath('code', 'business_rule_violated')
            ->assertJsonValidationErrors(['provider']);
    }

    public function test_customer_cannot_retry_another_customers_payment(): void
    {
        Http::fake([
            'sandbox.nmb.test/*/session' => Http::response([
                'result' => 'SUCCESS',
                'session' => ['id' => 'SESSION-X', 'successIndicator' => 'ind-x'],
            ]),
        ]);

        $owner = User::factory()->create();
        $other = User::factory()->create();
        $order = $this->createPayableOrder($owner, ['total' => 9000]);

        Sanctum::actingAs($owner);
        $transactionId = $this->postJson("/api/v1/payments/start/{$order->id}")
            ->assertCreated()
            ->json('data.id');

        Sanctum::actingAs($other);
        $this->postJson("/api/v1/payments/{$transactionId}/nmb/checkout-session")
            ->assertNotFound()
            ->assertJsonPath('code', 'not_found');
    }

    public function test_double_click_retry_serializes_to_one_fresh_session_call_chain(): void
    {
        Http::fake([
            'sandbox.nmb.test/*/session' => Http::sequence()
                ->push([
                    'result' => 'SUCCESS',
                    'session' => [
                        'id' => 'SESSION-START',
                        'successIndicator' => 'ind-start',
                    ],
                ])
                ->push([
                    'result' => 'SUCCESS',
                    'session' => [
                        'id' => 'SESSION-RETRY-1',
                        'successIndicator' => 'ind-retry-1',
                    ],
                ])
                ->push([
                    'result' => 'SUCCESS',
                    'session' => [
                        'id' => 'SESSION-RETRY-2',
                        'successIndicator' => 'ind-retry-2',
                    ],
                ]),
        ]);

        $user = User::factory()->create();
        $order = $this->createPayableOrder($user, ['total' => 18000]);
        Sanctum::actingAs($user);

        $transactionId = $this->postJson("/api/v1/payments/start/{$order->id}")
            ->assertCreated()
            ->json('data.id');

        $first = $this->postJson("/api/v1/payments/{$transactionId}/nmb/checkout-session")->assertOk();
        $second = $this->postJson("/api/v1/payments/{$transactionId}/nmb/checkout-session")->assertOk();

        $this->assertSame($transactionId, $first->json('data.id'));
        $this->assertSame($transactionId, $second->json('data.id'));
        $this->assertDatabaseCount('payment_transactions', 1);
        $this->assertSame('SESSION-RETRY-2', PaymentTransaction::query()->findOrFail($transactionId)->provider_reference);
        $this->assertNotSame('SESSION-START', $second->json('data.provider_reference'));
    }

    public function test_normal_payment_start_still_creates_single_transaction(): void
    {
        Http::fake([
            'sandbox.nmb.test/*/session' => Http::response([
                'result' => 'SUCCESS',
                'session' => [
                    'id' => 'SESSION-FIRST',
                    'successIndicator' => 'ind-first',
                ],
            ]),
        ]);

        $user = User::factory()->create();
        $order = $this->createPayableOrder($user, ['total' => 11000]);
        Sanctum::actingAs($user);

        $first = $this->postJson("/api/v1/payments/start/{$order->id}")->assertCreated();
        $second = $this->postJson("/api/v1/payments/start/{$order->id}")->assertCreated();

        $this->assertSame($first->json('data.id'), $second->json('data.id'));
        $this->assertSame('SESSION-FIRST', $first->json('data.provider_reference'));
        $this->assertDatabaseCount('payment_transactions', 1);
    }
}
