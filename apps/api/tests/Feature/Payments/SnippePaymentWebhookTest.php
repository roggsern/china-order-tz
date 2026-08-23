<?php

namespace Tests\Feature\Payments;

use App\Enums\OrderStatus;
use App\Enums\PaymentProvider;
use App\Enums\PaymentTransactionStatus;
use App\Models\Order;
use App\Models\PaymentTransaction;
use App\Models\User;
use App\Payments\Gateways\Snippe\SnippeReplayGuard;
use App\Services\Settings\SettingsService;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SnippePaymentWebhookTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(SettingsSeeder::class);
        Cache::flush();

        config([
            'payments.snippe.enabled' => true,
            'payments.snippe.base_url' => 'https://api.snippe.test',
            'payments.snippe.api_key' => 'snippe-test-api-key',
            'payments.snippe.webhook_secret' => 'whsec_test_webhook_secret',
            'payments.snippe.webhook_url' => 'https://example.test/api/v1/payments/snippe/webhook',
            'payments.snippe.webhook_max_age_seconds' => 300,
            'payments.snippe.webhook_max_future_skew_seconds' => 60,
            'payments.snippe.webhook_replay_ttl_seconds' => 86400,
            'payments.snippe.http_retry_times' => 0,
        ]);
    }

    public function test_valid_signed_webhook_is_accepted_and_triggers_get_verification(): void
    {
        $transaction = $this->createSnippeTransaction(providerReference: 'pi_webhook_ok');
        $getCalled = false;

        Http::fake([
            'api.snippe.test/v1/payments/pi_webhook_ok' => function () use (&$getCalled, $transaction) {
                $getCalled = true;

                return Http::response([
                    'status' => 'success',
                    'data' => [
                        'reference' => 'pi_webhook_ok',
                        'status' => 'completed',
                        'amount' => ['value' => 45000, 'currency' => 'TZS'],
                        'metadata' => ['merchant_reference' => $transaction->merchant_reference],
                    ],
                ]);
            },
        ]);

        $payload = $this->webhookPayload(
            eventId: 'evt_completed_1',
            eventType: 'payment.completed',
            reference: 'pi_webhook_ok',
            status: 'completed',
            transaction: $transaction,
        );

        $this->postSignedWebhook($payload)->assertOk();
        $this->assertTrue($getCalled);
        $this->assertSame(OrderStatus::Paid, $transaction->order->fresh()->status);
    }

    public function test_webhook_payload_alone_cannot_settle_order_without_get_verification(): void
    {
        $transaction = $this->createSnippeTransaction(providerReference: 'pi_pending_only');

        Http::fake([
            'api.snippe.test/v1/payments/pi_pending_only' => Http::response([
                'status' => 'success',
                'data' => [
                    'reference' => 'pi_pending_only',
                    'status' => 'pending',
                    'amount' => ['value' => 45000, 'currency' => 'TZS'],
                ],
            ]),
        ]);

        $payload = $this->webhookPayload(
            eventId: 'evt_completed_pending',
            eventType: 'payment.completed',
            reference: 'pi_pending_only',
            status: 'completed',
            transaction: $transaction,
        );

        $this->postSignedWebhook($payload)->assertOk();
        $this->assertSame(OrderStatus::PendingPayment, $transaction->order->fresh()->status);
    }

    public function test_invalid_signature_is_rejected(): void
    {
        $payload = $this->webhookPayload('evt_bad_sig', 'payment.completed', 'pi_bad', 'completed');

        $this->postSignedWebhook($payload, signatureOverride: str_repeat('a', 64))
            ->assertStatus(400);
    }

    public function test_missing_signature_is_rejected(): void
    {
        $rawBody = json_encode($this->webhookPayload('evt_missing_sig', 'payment.completed', 'pi_missing', 'completed'));

        $this->call(
            'POST',
            '/api/v1/payments/snippe/webhook',
            [],
            [],
            [],
            $this->transformHeadersToServerVars([
                'X-Webhook-Timestamp' => (string) time(),
                'Content-Type' => 'application/json',
            ]),
            $rawBody,
        )->assertStatus(400);
    }

    public function test_stale_timestamp_is_rejected(): void
    {
        $payload = $this->webhookPayload('evt_stale', 'payment.completed', 'pi_stale', 'completed');
        $timestamp = time() - 400;

        $this->postSignedWebhook($payload, timestamp: $timestamp)->assertStatus(400);
    }

    public function test_wrong_verified_amount_cannot_settle(): void
    {
        $transaction = $this->createSnippeTransaction(providerReference: 'pi_wrong_amount');

        Http::fake([
            'api.snippe.test/v1/payments/pi_wrong_amount' => Http::response([
                'status' => 'success',
                'data' => [
                    'reference' => 'pi_wrong_amount',
                    'status' => 'completed',
                    'amount' => ['value' => 1, 'currency' => 'TZS'],
                ],
            ]),
        ]);

        $payload = $this->webhookPayload(
            'evt_wrong_amount',
            'payment.completed',
            'pi_wrong_amount',
            'completed',
            $transaction,
        );

        $this->postSignedWebhook($payload)->assertOk();
        $this->assertSame(OrderStatus::PendingPayment, $transaction->order->fresh()->status);
    }

    public function test_wrong_verified_currency_cannot_settle(): void
    {
        $transaction = $this->createSnippeTransaction(providerReference: 'pi_wrong_currency');

        Http::fake([
            'api.snippe.test/v1/payments/pi_wrong_currency' => Http::response([
                'status' => 'success',
                'data' => [
                    'reference' => 'pi_wrong_currency',
                    'status' => 'completed',
                    'amount' => ['value' => 45000, 'currency' => 'USD'],
                ],
            ]),
        ]);

        $payload = $this->webhookPayload(
            'evt_wrong_currency',
            'payment.completed',
            'pi_wrong_currency',
            'completed',
            $transaction,
        );

        $this->postSignedWebhook($payload)->assertOk();
        $this->assertSame(OrderStatus::PendingPayment, $transaction->order->fresh()->status);
    }

    public function test_wrong_provider_reference_cannot_settle(): void
    {
        $transaction = $this->createSnippeTransaction(providerReference: 'pi_expected');

        Http::fake([
            'api.snippe.test/v1/payments/pi_expected' => Http::response([
                'status' => 'success',
                'data' => [
                    'reference' => 'pi_other',
                    'status' => 'completed',
                    'amount' => ['value' => 45000, 'currency' => 'TZS'],
                ],
            ]),
        ]);

        $payload = $this->webhookPayload(
            'evt_wrong_ref',
            'payment.completed',
            'pi_expected',
            'completed',
            $transaction,
        );

        $this->postSignedWebhook($payload)->assertOk();
        $this->assertSame(OrderStatus::PendingPayment, $transaction->order->fresh()->status);
    }

    public function test_wrong_merchant_reference_cannot_settle(): void
    {
        $transaction = $this->createSnippeTransaction(providerReference: 'pi_merchant_mismatch');

        Http::fake([
            'api.snippe.test/v1/payments/pi_merchant_mismatch' => Http::response([
                'status' => 'success',
                'data' => [
                    'reference' => 'pi_merchant_mismatch',
                    'status' => 'completed',
                    'amount' => ['value' => 45000, 'currency' => 'TZS'],
                    'metadata' => ['merchant_reference' => 'COTZ-PAY-WRONG-000001'],
                ],
            ]),
        ]);

        $payload = $this->webhookPayload(
            'evt_merchant_mismatch',
            'payment.completed',
            'pi_merchant_mismatch',
            'completed',
            $transaction,
        );

        $this->postSignedWebhook($payload)->assertOk();
        $this->assertSame(OrderStatus::PendingPayment, $transaction->order->fresh()->status);
    }

    public function test_unknown_transaction_returns_retryable_response_and_is_not_deduped(): void
    {
        Http::fake();

        $payload = $this->webhookPayload(
            'evt_unknown_txn',
            'payment.completed',
            'pi_unknown',
            'completed',
        );

        $this->postSignedWebhook($payload)->assertStatus(503);
        Http::assertNothingSent();
        $this->assertFalse(app(SnippeReplayGuard::class)->hasSuccessfulDelivery('evt_unknown_txn'));
    }

    public function test_unknown_transaction_can_be_processed_after_transaction_becomes_visible(): void
    {
        Http::fake([
            'api.snippe.test/v1/payments/pi_retry_visible' => Http::response([
                'status' => 'success',
                'data' => [
                    'reference' => 'pi_retry_visible',
                    'status' => 'completed',
                    'amount' => ['value' => 45000, 'currency' => 'TZS'],
                ],
            ]),
        ]);

        $payload = $this->webhookPayload(
            'evt_retry_visible',
            'payment.completed',
            'pi_retry_visible',
            'completed',
        );

        $this->postSignedWebhook($payload)->assertStatus(503);
        $this->assertFalse(app(SnippeReplayGuard::class)->hasSuccessfulDelivery('evt_retry_visible'));

        $transaction = $this->createSnippeTransaction(providerReference: 'pi_retry_visible');

        $payload = $this->webhookPayload(
            'evt_retry_visible',
            'payment.completed',
            'pi_retry_visible',
            'completed',
            $transaction,
        );

        $this->postSignedWebhook($payload)->assertOk();
        $this->assertTrue(app(SnippeReplayGuard::class)->hasSuccessfulDelivery('evt_retry_visible'));
        $this->assertSame(OrderStatus::Paid, $transaction->order->fresh()->status);
    }

    public function test_snippe_webhook_cannot_mutate_nmb_transaction(): void
    {
        $order = Order::factory()->create([
            'status' => OrderStatus::PendingPayment,
            'total' => 45000,
            'currency' => 'TZS',
        ]);

        PaymentTransaction::factory()->processing()->create([
            'order_id' => $order->id,
            'provider' => PaymentProvider::Nmb,
            'provider_reference' => 'SESSION-NMB-ONLY',
            'merchant_reference' => 'COTZ-PAY-NMB-000001',
            'amount' => 45000,
            'currency' => 'TZS',
        ]);

        Http::fake();

        $payload = $this->webhookPayload(
            'evt_nmb_collision',
            'payment.completed',
            'SESSION-NMB-ONLY',
            'completed',
        );

        $this->postSignedWebhook($payload)->assertStatus(503);
        $this->assertSame(OrderStatus::PendingPayment, $order->fresh()->status);
        Http::assertNothingSent();
    }

    public function test_payment_failed_maps_safely(): void
    {
        $transaction = $this->createSnippeTransaction(providerReference: 'pi_failed');

        Http::fake([
            'api.snippe.test/v1/payments/pi_failed' => Http::response([
                'status' => 'success',
                'data' => [
                    'reference' => 'pi_failed',
                    'status' => 'failed',
                    'failure_reason' => 'declined',
                ],
            ]),
        ]);

        $payload = $this->webhookPayload('evt_failed', 'payment.failed', 'pi_failed', 'failed', $transaction);

        $this->postSignedWebhook($payload)->assertOk();
        $this->assertSame(PaymentTransactionStatus::Failed, $transaction->fresh()->status);
        $this->assertSame(OrderStatus::PendingPayment, $transaction->order->fresh()->status);
    }

    public function test_payment_expired_maps_to_failed_with_reason(): void
    {
        $transaction = $this->createSnippeTransaction(providerReference: 'pi_expired');

        Http::fake([
            'api.snippe.test/v1/payments/pi_expired' => Http::response([
                'status' => 'success',
                'data' => [
                    'reference' => 'pi_expired',
                    'status' => 'expired',
                ],
            ]),
        ]);

        $payload = $this->webhookPayload('evt_expired', 'payment.expired', 'pi_expired', 'expired', $transaction);

        $this->postSignedWebhook($payload)->assertOk();

        $fresh = $transaction->fresh();
        $this->assertSame(PaymentTransactionStatus::Failed, $fresh->status);
        $this->assertSame('expired', $fresh->verification_payload['failure_reason'] ?? null);
    }

    public function test_payment_voided_maps_to_cancelled(): void
    {
        $transaction = $this->createSnippeTransaction(providerReference: 'pi_voided');

        Http::fake([
            'api.snippe.test/v1/payments/pi_voided' => Http::response([
                'status' => 'success',
                'data' => [
                    'reference' => 'pi_voided',
                    'status' => 'voided',
                ],
            ]),
        ]);

        $payload = $this->webhookPayload('evt_voided', 'payment.voided', 'pi_voided', 'voided', $transaction);

        $this->postSignedWebhook($payload)->assertOk();
        $this->assertSame(PaymentTransactionStatus::Cancelled, $transaction->fresh()->status);
    }

    public function test_unknown_event_cannot_settle(): void
    {
        $transaction = $this->createSnippeTransaction();

        Http::fake();

        $payload = $this->webhookPayload('evt_unknown', 'payout.completed', 'pi_unknown_event', 'completed', $transaction);

        $this->postSignedWebhook($payload)->assertOk();
        $this->assertSame(OrderStatus::PendingPayment, $transaction->order->fresh()->status);
        Http::assertNothingSent();
    }

    public function test_duplicate_event_returns_success_without_duplicate_settlement(): void
    {
        $transaction = $this->createSnippeTransaction(providerReference: 'pi_dup');

        Http::fake([
            'api.snippe.test/v1/payments/pi_dup' => Http::response([
                'status' => 'success',
                'data' => [
                    'reference' => 'pi_dup',
                    'status' => 'completed',
                    'amount' => ['value' => 45000, 'currency' => 'TZS'],
                    'metadata' => ['merchant_reference' => $transaction->merchant_reference],
                ],
            ]),
        ]);

        $payload = $this->webhookPayload('evt_dup', 'payment.completed', 'pi_dup', 'completed', $transaction);

        $this->postSignedWebhook($payload)->assertOk();
        $this->postSignedWebhook($payload)->assertOk();

        $this->assertSame(OrderStatus::Paid, $transaction->order->fresh()->status);
        Http::assertSentCount(1);
    }

    public function test_webhook_first_then_refresh_is_safe(): void
    {
        $transaction = $this->createSnippeTransaction(providerReference: 'pi_race_webhook');

        Http::fake([
            'api.snippe.test/v1/payments/pi_race_webhook' => Http::response([
                'status' => 'success',
                'data' => [
                    'reference' => 'pi_race_webhook',
                    'status' => 'completed',
                    'amount' => ['value' => 45000, 'currency' => 'TZS'],
                    'metadata' => ['merchant_reference' => $transaction->merchant_reference],
                ],
            ]),
        ]);

        $payload = $this->webhookPayload(
            'evt_race_webhook',
            'payment.completed',
            'pi_race_webhook',
            'completed',
            $transaction,
        );

        $this->postSignedWebhook($payload)->assertOk();

        Sanctum::actingAs($transaction->order->user);

        $this->postJson("/api/v1/payments/{$transaction->id}/refresh")
            ->assertOk()
            ->assertJsonPath('data.status', 'successful');
    }

    public function test_refresh_first_then_webhook_is_safe(): void
    {
        $transaction = $this->createSnippeTransaction(providerReference: 'pi_race_refresh');

        Http::fake([
            'api.snippe.test/v1/payments/pi_race_refresh' => Http::response([
                'status' => 'success',
                'data' => [
                    'reference' => 'pi_race_refresh',
                    'status' => 'completed',
                    'amount' => ['value' => 45000, 'currency' => 'TZS'],
                    'metadata' => ['merchant_reference' => $transaction->merchant_reference],
                ],
            ]),
        ]);

        Sanctum::actingAs($transaction->order->user);
        $this->postJson("/api/v1/payments/{$transaction->id}/refresh")
            ->assertOk()
            ->assertJsonPath('data.status', 'successful');

        $payload = $this->webhookPayload(
            'evt_race_refresh',
            'payment.completed',
            'pi_race_refresh',
            'completed',
            $transaction,
        );

        $this->postSignedWebhook($payload)->assertOk();
        $this->assertSame(OrderStatus::Paid, $transaction->order->fresh()->status);
    }

    public function test_transient_snippe_get_failure_is_retryable_and_not_deduped(): void
    {
        $transaction = $this->createSnippeTransaction(providerReference: 'pi_retry');

        Http::fake([
            'api.snippe.test/v1/payments/pi_retry' => Http::sequence()
                ->push([
                    'status' => 'error',
                    'code' => 503,
                    'message' => 'temporary',
                ], 503)
                ->push([
                    'status' => 'success',
                    'data' => [
                        'reference' => 'pi_retry',
                        'status' => 'completed',
                        'amount' => ['value' => 45000, 'currency' => 'TZS'],
                        'metadata' => ['merchant_reference' => $transaction->merchant_reference],
                    ],
                ], 200),
        ]);

        $payload = $this->webhookPayload('evt_retry', 'payment.completed', 'pi_retry', 'completed', $transaction);

        $this->postSignedWebhook($payload)->assertStatus(503);
        $this->assertFalse(app(SnippeReplayGuard::class)->hasSuccessfulDelivery('evt_retry'));

        $this->postSignedWebhook($payload)->assertOk();
        $this->assertSame(OrderStatus::Paid, $transaction->order->fresh()->status);
    }

    public function test_snippe_429_during_verification_is_retryable(): void
    {
        $transaction = $this->createSnippeTransaction(providerReference: 'pi_429');

        Http::fake([
            'api.snippe.test/v1/payments/pi_429' => Http::response([
                'status' => 'error',
                'code' => 429,
                'message' => 'rate limited',
            ], 429),
        ]);

        $payload = $this->webhookPayload('evt_429', 'payment.completed', 'pi_429', 'completed', $transaction);

        $this->postSignedWebhook($payload)->assertStatus(503);
        $this->assertFalse(app(SnippeReplayGuard::class)->hasSuccessfulDelivery('evt_429'));
    }

    public function test_conflicting_metadata_merchant_reference_does_not_settle(): void
    {
        $transaction = $this->createSnippeTransaction(providerReference: 'pi_meta_mismatch');

        Http::fake();

        $payload = $this->webhookPayload(
            'evt_meta_mismatch',
            'payment.completed',
            'pi_meta_mismatch',
            'completed',
            $transaction,
        );
        $payload['data']['external_reference'] = 'S20388368013';
        $payload['data']['metadata']['merchant_reference'] = 'COTZ-PAY-OTHER';

        $this->postSignedWebhook($payload)->assertStatus(400);
        $this->assertSame(OrderStatus::PendingPayment, $transaction->order->fresh()->status);
        Http::assertNothingSent();
    }

    public function test_provider_generated_external_reference_still_settles(): void
    {
        $transaction = $this->createSnippeTransaction(providerReference: 'pi_ext_ref');

        Http::fake([
            'api.snippe.test/v1/payments/pi_ext_ref' => Http::response([
                'status' => 'success',
                'data' => [
                    'reference' => 'pi_ext_ref',
                    'external_reference' => 'S20388368013',
                    'status' => 'completed',
                    'amount' => ['value' => 45000, 'currency' => 'TZS'],
                    'metadata' => [
                        'merchant_reference' => $transaction->merchant_reference,
                        'payment_transaction_id' => $transaction->id,
                    ],
                ],
            ]),
        ]);

        $payload = $this->webhookPayload(
            'evt_ext_ref',
            'payment.completed',
            'pi_ext_ref',
            'completed',
            $transaction,
        );
        $payload['data']['external_reference'] = 'S20388368013';

        $this->postSignedWebhook($payload)->assertOk();
        $this->assertSame(OrderStatus::Paid, $transaction->order->fresh()->status);
    }

    public function test_webhook_settles_after_admin_disables_snippe(): void
    {
        $transaction = $this->createSnippeTransaction(providerReference: 'pi_admin_disabled');

        app(SettingsService::class)->set('payments.enabled_methods', [
            'nmb' => true,
            'snippe' => false,
            'mpesa' => false,
            'card' => false,
            'cash' => false,
            'bank_transfer' => false,
        ]);
        Cache::flush();

        Http::fake([
            'api.snippe.test/v1/payments/pi_admin_disabled' => Http::response([
                'status' => 'success',
                'data' => [
                    'reference' => 'pi_admin_disabled',
                    'external_reference' => 'S20388368013',
                    'status' => 'completed',
                    'amount' => ['value' => 45000, 'currency' => 'TZS'],
                    'metadata' => [
                        'merchant_reference' => $transaction->merchant_reference,
                        'payment_transaction_id' => $transaction->id,
                    ],
                ],
            ]),
        ]);

        $payload = $this->webhookPayload(
            'evt_admin_disabled',
            'payment.completed',
            'pi_admin_disabled',
            'completed',
            $transaction,
        );

        $this->postSignedWebhook($payload)->assertOk();
        $this->assertSame(OrderStatus::Paid, $transaction->order->fresh()->status);
    }

    public function test_secrets_and_full_phone_are_not_exposed_in_response(): void
    {
        $transaction = $this->createSnippeTransaction(providerReference: 'pi_secret');

        Http::fake([
            'api.snippe.test/v1/payments/pi_secret' => Http::response([
                'status' => 'success',
                'data' => [
                    'reference' => 'pi_secret',
                    'status' => 'completed',
                    'amount' => ['value' => 45000, 'currency' => 'TZS'],
                    'metadata' => ['merchant_reference' => $transaction->merchant_reference],
                    'customer' => ['phone' => '+255712345678'],
                ],
            ]),
        ]);

        $payload = $this->webhookPayload(
            'evt_secret',
            'payment.completed',
            'pi_secret',
            'completed',
            $transaction,
            phone: '+255712345678',
        );

        $response = $this->postSignedWebhook($payload)->assertOk();
        $body = $response->getContent();

        $this->assertStringNotContainsString('whsec_test_webhook_secret', $body);
        $this->assertStringNotContainsString('snippe-test-api-key', $body);
        $this->assertStringNotContainsString('255712345678', (string) json_encode($transaction->fresh()->verification_payload));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function postSignedWebhook(
        array $payload,
        ?int $timestamp = null,
        ?string $signatureOverride = null,
    ): TestResponse {
        $rawBody = json_encode($payload, JSON_THROW_ON_ERROR);
        $timestamp ??= time();
        $secret = (string) config('payments.snippe.webhook_secret');
        $signature = $signatureOverride ?? hash_hmac('sha256', "{$timestamp}.{$rawBody}", $secret);

        return $this->call(
            'POST',
            '/api/v1/payments/snippe/webhook',
            [],
            [],
            [],
            $this->transformHeadersToServerVars([
                'X-Webhook-Timestamp' => (string) $timestamp,
                'X-Webhook-Signature' => $signature,
                'Content-Type' => 'application/json',
                'Accept' => 'text/plain',
            ]),
            $rawBody,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function webhookPayload(
        string $eventId,
        string $eventType,
        string $reference,
        string $status,
        ?PaymentTransaction $transaction = null,
        ?string $phone = null,
    ): array {
        $metadata = [];
        if ($transaction !== null) {
            $metadata = [
                'merchant_reference' => $transaction->merchant_reference,
                'payment_transaction_id' => $transaction->id,
                'order_id' => $transaction->order_id,
            ];
        }

        $data = [
            'reference' => $reference,
            'status' => $status,
            'amount' => ['value' => 45000, 'currency' => 'TZS'],
            'metadata' => $metadata,
        ];

        if ($phone !== null) {
            $data['customer'] = ['phone' => $phone];
        }

        return [
            'id' => $eventId,
            'type' => $eventType,
            'api_version' => '2026-01-25',
            'created_at' => now()->toIso8601String(),
            'data' => $data,
        ];
    }

    private function createSnippeTransaction(?string $providerReference = 'pi_default'): PaymentTransaction
    {
        $user = User::factory()->create();
        $order = $this->createPayableOrder($user, [
            'total' => 45000,
            'currency' => 'TZS',
        ]);

        return PaymentTransaction::factory()->processing()->create([
            'order_id' => $order->id,
            'provider' => PaymentProvider::Snippe,
            'provider_reference' => $providerReference,
            'amount' => 45000,
            'currency' => 'TZS',
        ])->load('order');
    }
}
