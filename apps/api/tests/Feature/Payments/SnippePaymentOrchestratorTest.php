<?php

namespace Tests\Feature\Payments;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentProvider;
use App\Enums\PaymentTransactionStatus;
use App\Models\Order;
use App\Models\PaymentTransaction;
use App\Models\ShippingAddress;
use App\Models\User;
use App\Payments\Gateways\Snippe\SnippeIdempotencyKey;
use App\Services\Payments\Orchestration\PaymentOrchestrator;
use App\Services\Payments\Orchestration\Providers\NmbPaymentProvider;
use App\Services\Payments\Orchestration\Providers\SnippePaymentProvider;
use App\Services\Payments\PaymentConfigurationResolver;
use App\Services\Settings\SettingsService;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SnippePaymentOrchestratorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(SettingsSeeder::class);
        Cache::flush();

        $this->configureNmb();
        $this->configureSnippe();
        $this->enableSnippeInSettings();
    }

    public function test_snippe_provider_resolves_through_orchestrator_di(): void
    {
        /** @var PaymentOrchestrator $orchestrator */
        $orchestrator = app(PaymentOrchestrator::class);

        $this->assertContains('snippe', $orchestrator->registeredProviders());
        $this->assertInstanceOf(SnippePaymentProvider::class, $orchestrator->resolveProvider('snippe'));
    }

    public function test_nmb_provider_still_resolves_normally(): void
    {
        /** @var PaymentOrchestrator $orchestrator */
        $orchestrator = app(PaymentOrchestrator::class);

        $this->assertInstanceOf(NmbPaymentProvider::class, $orchestrator->resolveProvider('nmb'));
    }

    public function test_snippe_disabled_or_missing_configuration_fails_closed(): void
    {
        config([
            'payments.snippe.enabled' => false,
            'payments.snippe.api_key' => '',
        ]);

        $resolver = app(PaymentConfigurationResolver::class);
        $this->assertFalse($resolver->isProviderAvailable(PaymentMethod::Snippe->value));

        $user = User::factory()->create();
        $order = $this->createPayableOrder($user, ['total' => 45000]);

        Sanctum::actingAs($user);

        Http::fake();

        $this->postJson("/api/v1/payments/start/{$order->id}", [
            'provider' => PaymentMethod::Snippe->value,
            'phone_number' => '0712345678',
        ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'failed');

        Http::assertNothingSent();
    }

    public function test_start_requires_phone_when_provider_is_snippe(): void
    {
        $user = User::factory()->create();
        $order = $this->createPayableOrder($user, ['total' => 45000]);

        Sanctum::actingAs($user);

        $this->postJson("/api/v1/payments/start/{$order->id}", [
            'provider' => PaymentMethod::Snippe->value,
        ])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'payment_failed')
            ->assertJsonValidationErrors(['phone_number']);
    }

    public function test_nmb_start_does_not_require_phone(): void
    {
        $user = User::factory()->create();
        $order = $this->createPayableOrder($user, ['total' => 45000]);

        Sanctum::actingAs($user);

        $this->postJson("/api/v1/payments/start/{$order->id}", [
            'provider' => PaymentMethod::Nmb->value,
        ])
            ->assertCreated()
            ->assertJsonPath('data.provider', 'nmb');
    }

    public function test_invalid_phone_is_rejected_for_snippe(): void
    {
        $user = User::factory()->create();
        $order = $this->createPayableOrder($user, ['total' => 45000]);

        Sanctum::actingAs($user);

        $this->postJson("/api/v1/payments/start/{$order->id}", [
            'provider' => PaymentMethod::Snippe->value,
            'phone_number' => 'invalid',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['phone_number']);
    }

    public function test_non_tzs_snippe_payment_is_rejected(): void
    {
        $user = User::factory()->create();
        $order = $this->createPayableOrder($user, [
            'total' => 45000,
            'currency' => 'USD',
        ]);

        Sanctum::actingAs($user);

        $this->postJson("/api/v1/payments/start/{$order->id}", [
            'provider' => PaymentMethod::Snippe->value,
            'phone_number' => '0712345678',
        ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'failed');
    }

    public function test_amount_below_500_is_rejected_for_snippe(): void
    {
        $user = User::factory()->create();
        $order = $this->createPayableOrder($user, ['total' => 499]);

        Sanctum::actingAs($user);

        $this->postJson("/api/v1/payments/start/{$order->id}", [
            'provider' => PaymentMethod::Snippe->value,
            'phone_number' => '0712345678',
        ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'failed');
    }

    public function test_fractional_tzs_amount_is_rejected_for_snippe(): void
    {
        $user = User::factory()->create();
        $order = $this->createPayableOrder($user, ['total' => '45000.50']);

        Sanctum::actingAs($user);

        $this->postJson("/api/v1/payments/start/{$order->id}", [
            'provider' => PaymentMethod::Snippe->value,
            'phone_number' => '0712345678',
        ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'failed');
    }

    public function test_snippe_create_request_uses_bearer_auth_correct_endpoint_and_payload(): void
    {
        Http::fake([
            'api.snippe.test/v1/payments' => Http::response([
                'status' => 'success',
                'code' => 201,
                'data' => [
                    'reference' => 'pi_snippe_ref_1',
                    'status' => 'pending',
                    'amount' => ['value' => 45000, 'currency' => 'TZS'],
                ],
            ], 201),
        ]);

        $user = User::factory()->create();
        $order = $this->createPayableOrder($user, ['total' => 45000]);

        Sanctum::actingAs($user);

        $response = $this->postJson("/api/v1/payments/start/{$order->id}", [
            'provider' => PaymentMethod::Snippe->value,
            'phone_number' => '+255712345678',
        ])->assertCreated();

        $this->assertSame('processing', $response->json('data.status'));
        $this->assertSame('pi_snippe_ref_1', $response->json('data.provider_reference'));

        Http::assertSent(function ($request) use ($order) {
            if (! str_contains($request->url(), 'api.snippe.test/v1/payments')) {
                return false;
            }

            $this->assertSame('Bearer snippe-test-api-key', $request->header('Authorization')[0] ?? '');
            $this->assertArrayHasKey('Idempotency-Key', $request->headers());
            $this->assertLessThanOrEqual(30, strlen($request->header('Idempotency-Key')[0] ?? ''));

            $body = $request->data();
            $this->assertSame('mobile', $body['payment_type'] ?? null);
            $this->assertSame(45000, $body['details']['amount'] ?? null);
            $this->assertSame('TZS', $body['details']['currency'] ?? null);
            $this->assertSame('255712345678', $body['phone_number'] ?? null);
            $this->assertSame($order->id, $body['metadata']['order_id'] ?? null);

            return true;
        });

        $transactionId = $response->json('data.id');
        $expectedKey = SnippeIdempotencyKey::forPaymentTransaction($transactionId);
        $recordedKey = collect(Http::recorded())->first()[0]->header('Idempotency-Key')[0] ?? '';
        $this->assertSame($expectedKey, $recordedKey);
    }

    public function test_same_transaction_retry_reuses_same_idempotency_key(): void
    {
        Http::fake([
            'api.snippe.test/v1/payments' => Http::response([
                'status' => 'success',
                'code' => 201,
                'data' => [
                    'reference' => 'pi_snippe_ref_retry',
                    'status' => 'pending',
                ],
            ], 201),
        ]);

        $user = User::factory()->create();
        $order = $this->createPayableOrder($user, ['total' => 45000]);

        Sanctum::actingAs($user);

        $first = $this->postJson("/api/v1/payments/start/{$order->id}", [
            'provider' => PaymentMethod::Snippe->value,
            'phone_number' => '0712345678',
        ])->assertCreated();

        $second = $this->postJson("/api/v1/payments/start/{$order->id}", [
            'provider' => PaymentMethod::Snippe->value,
            'phone_number' => '0712345678',
        ])->assertCreated();

        $this->assertSame($first->json('data.id'), $second->json('data.id'));
        $this->assertCount(1, Http::recorded());
    }

    public function test_different_transactions_produce_different_idempotency_keys(): void
    {
        Http::fake([
            'api.snippe.test/v1/payments' => Http::sequence()
                ->push([
                    'status' => 'success',
                    'code' => 201,
                    'data' => ['reference' => 'pi_one', 'status' => 'pending'],
                ], 201)
                ->push([
                    'status' => 'success',
                    'code' => 201,
                    'data' => ['reference' => 'pi_two', 'status' => 'pending'],
                ], 201),
        ]);

        $user = User::factory()->create();
        $orderA = $this->createPayableOrder($user, ['total' => 45000]);
        $orderB = $this->createPayableOrder($user, ['total' => 46000]);

        Sanctum::actingAs($user);

        $txnA = $this->postJson("/api/v1/payments/start/{$orderA->id}", [
            'provider' => PaymentMethod::Snippe->value,
            'phone_number' => '0712345678',
        ])->json('data.id');

        $txnB = $this->postJson("/api/v1/payments/start/{$orderB->id}", [
            'provider' => PaymentMethod::Snippe->value,
            'phone_number' => '0712345678',
        ])->json('data.id');

        $keys = collect(Http::recorded())->map(fn (array $pair) => $pair[0]->header('Idempotency-Key')[0] ?? '')->all();

        $this->assertNotSame($keys[0], $keys[1]);
        $this->assertSame(SnippeIdempotencyKey::forPaymentTransaction($txnA), $keys[0]);
        $this->assertSame(SnippeIdempotencyKey::forPaymentTransaction($txnB), $keys[1]);
    }

    public function test_completed_verification_maps_to_successful_when_values_match(): void
    {
        Http::fake([
            'api.snippe.test/v1/payments' => Http::response([
                'status' => 'success',
                'code' => 201,
                'data' => ['reference' => 'pi_verify_ok', 'status' => 'pending'],
            ], 201),
            'api.snippe.test/v1/payments/pi_verify_ok' => Http::response([
                'status' => 'success',
                'code' => 200,
                'data' => [
                    'reference' => 'pi_verify_ok',
                    'status' => 'completed',
                    'amount' => ['value' => 45000, 'currency' => 'TZS'],
                ],
            ]),
        ]);

        $user = User::factory()->create();
        $order = $this->createPayableOrder($user, ['total' => 45000]);

        Sanctum::actingAs($user);

        $start = $this->postJson("/api/v1/payments/start/{$order->id}", [
            'provider' => PaymentMethod::Snippe->value,
            'phone_number' => '0712345678',
        ])->assertCreated();

        $transactionId = $start->json('data.id');

        $this->postJson("/api/v1/payments/{$transactionId}/refresh")
            ->assertOk()
            ->assertJsonPath('data.status', 'successful');

        $this->assertSame(OrderStatus::Paid, $order->fresh()->status);
    }

    public function test_wrong_amount_on_verification_cannot_succeed(): void
    {
        $transaction = $this->startSnippeTransaction();

        Http::fake([
            'api.snippe.test/v1/payments/pi_wrong_amount' => Http::response([
                'status' => 'success',
                'code' => 200,
                'data' => [
                    'reference' => 'pi_wrong_amount',
                    'status' => 'completed',
                    'amount' => ['value' => 1, 'currency' => 'TZS'],
                ],
            ]),
        ]);

        $this->postJson("/api/v1/payments/{$transaction['id']}/refresh")
            ->assertOk()
            ->assertJsonPath('data.status', 'processing');
    }

    public function test_wrong_currency_on_verification_cannot_succeed(): void
    {
        $transaction = $this->startSnippeTransaction(reference: 'pi_wrong_currency');

        Http::fake([
            'api.snippe.test/v1/payments/pi_wrong_currency' => Http::response([
                'status' => 'success',
                'code' => 200,
                'data' => [
                    'reference' => 'pi_wrong_currency',
                    'status' => 'completed',
                    'amount' => ['value' => 45000, 'currency' => 'USD'],
                ],
            ]),
        ]);

        $this->postJson("/api/v1/payments/{$transaction['id']}/refresh")
            ->assertOk()
            ->assertJsonPath('data.status', 'processing');
    }

    public function test_wrong_reference_on_verification_cannot_succeed(): void
    {
        $transaction = $this->startSnippeTransaction(reference: 'pi_expected_ref');

        Http::fake([
            'api.snippe.test/v1/payments/pi_expected_ref' => Http::response([
                'status' => 'success',
                'code' => 200,
                'data' => [
                    'reference' => 'pi_other_ref',
                    'status' => 'completed',
                    'amount' => ['value' => 45000, 'currency' => 'TZS'],
                ],
            ]),
        ]);

        $this->postJson("/api/v1/payments/{$transaction['id']}/refresh")
            ->assertOk()
            ->assertJsonPath('data.status', 'processing');
    }

    public function test_failed_snippe_status_maps_to_failed(): void
    {
        $transaction = $this->startSnippeTransaction(reference: 'pi_failed');

        Http::fake([
            'api.snippe.test/v1/payments/pi_failed' => Http::response([
                'status' => 'success',
                'code' => 200,
                'data' => [
                    'reference' => 'pi_failed',
                    'status' => 'failed',
                    'failure_reason' => 'declined',
                ],
            ]),
        ]);

        $this->postJson("/api/v1/payments/{$transaction['id']}/refresh")
            ->assertOk()
            ->assertJsonPath('data.status', 'failed');
    }

    public function test_expired_snippe_status_maps_to_failed_with_reason_preserved(): void
    {
        $transaction = $this->startSnippeTransaction(reference: 'pi_expired');

        Http::fake([
            'api.snippe.test/v1/payments/pi_expired' => Http::response([
                'status' => 'success',
                'code' => 200,
                'data' => [
                    'reference' => 'pi_expired',
                    'status' => 'expired',
                ],
            ]),
        ]);

        $response = $this->postJson("/api/v1/payments/{$transaction['id']}/refresh")
            ->assertOk()
            ->assertJsonPath('data.status', 'failed');

        $this->assertSame('expired', $response->json('data.verification_payload.failure_reason'));
    }

    public function test_voided_snippe_status_maps_to_cancelled(): void
    {
        $transaction = $this->startSnippeTransaction(reference: 'pi_voided');

        Http::fake([
            'api.snippe.test/v1/payments/pi_voided' => Http::response([
                'status' => 'success',
                'code' => 200,
                'data' => [
                    'reference' => 'pi_voided',
                    'status' => 'voided',
                ],
            ]),
        ]);

        $this->postJson("/api/v1/payments/{$transaction['id']}/refresh")
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled');
    }

    public function test_provider_timeout_is_handled_safely(): void
    {
        Http::fake([
            'api.snippe.test/v1/payments' => fn () => throw new \Illuminate\Http\Client\ConnectionException('Timeout'),
        ]);

        $user = User::factory()->create();
        $order = $this->createPayableOrder($user, ['total' => 45000]);

        Sanctum::actingAs($user);

        $this->postJson("/api/v1/payments/start/{$order->id}", [
            'provider' => PaymentMethod::Snippe->value,
            'phone_number' => '0712345678',
        ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'failed');
    }

    public function test_snippe_4xx_is_handled_safely(): void
    {
        Http::fake([
            'api.snippe.test/v1/payments' => Http::response([
                'status' => 'error',
                'code' => 400,
                'message' => 'validation_error',
            ], 400),
        ]);

        $user = User::factory()->create();
        $order = $this->createPayableOrder($user, ['total' => 45000]);

        Sanctum::actingAs($user);

        $this->postJson("/api/v1/payments/start/{$order->id}", [
            'provider' => PaymentMethod::Snippe->value,
            'phone_number' => '0712345678',
        ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'failed');
    }

    public function test_snippe_5xx_is_handled_safely_on_refresh(): void
    {
        $transaction = $this->startSnippeTransaction(reference: 'pi_5xx');

        Http::fake([
            'api.snippe.test/v1/payments/pi_5xx' => Http::response([
                'status' => 'error',
                'code' => 500,
                'message' => 'server_error',
            ], 500),
        ]);

        $this->postJson("/api/v1/payments/{$transaction['id']}/refresh")
            ->assertOk()
            ->assertJsonPath('data.status', 'processing');
    }

    public function test_api_key_is_not_exposed_in_api_resource(): void
    {
        Http::fake([
            'api.snippe.test/v1/payments' => Http::response([
                'status' => 'success',
                'code' => 201,
                'data' => ['reference' => 'pi_secret_check', 'status' => 'pending'],
            ], 201),
        ]);

        $user = User::factory()->create([
            'first_name' => 'Jane',
            'last_name' => 'Buyer',
            'email' => 'jane@example.com',
        ]);
        $order = $this->createPayableOrder($user, ['total' => 45000]);

        Sanctum::actingAs($user);

        $payload = $this->postJson("/api/v1/payments/start/{$order->id}", [
            'provider' => PaymentMethod::Snippe->value,
            'phone_number' => '0712345678',
        ])->assertCreated()->json('data');

        $encoded = (string) json_encode($payload);
        $this->assertStringNotContainsString('snippe-test-api-key', $encoded);
        $this->assertStringNotContainsString('255712345678', $encoded);
        $this->assertStringContainsString('25571', (string) ($payload['request_payload']['phone_number'] ?? ''));
    }

    public function test_missing_first_name_on_shipping_snapshot_prevents_snippe_post(): void
    {
        Http::fake();

        $user = User::factory()->create([
            'first_name' => 'Fallback',
            'last_name' => 'Profile',
            'email' => 'fallback@example.com',
        ]);
        $order = $this->createPayableOrder($user, ['total' => 45000]);

        ShippingAddress::factory()->create([
            'order_id' => $order->id,
            'user_id' => $user->id,
            'first_name' => '',
            'last_name' => 'Recipient',
            'email' => 'snapshot@example.com',
        ]);

        Sanctum::actingAs($user);

        $this->postJson("/api/v1/payments/start/{$order->id}", [
            'provider' => PaymentMethod::Snippe->value,
            'phone_number' => '0712345678',
        ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'failed');

        Http::assertNothingSent();
        $this->assertSame(OrderStatus::PendingPayment, $order->fresh()->status);
    }

    public function test_missing_last_name_prevents_snippe_post(): void
    {
        Http::fake();

        $user = User::factory()->create([
            'first_name' => null,
            'last_name' => null,
            'name' => 'Madonna',
            'email' => 'madonna@example.com',
        ]);
        $order = $this->createPayableOrder($user, ['total' => 45000]);

        Sanctum::actingAs($user);

        $this->postJson("/api/v1/payments/start/{$order->id}", [
            'provider' => PaymentMethod::Snippe->value,
            'phone_number' => '0712345678',
        ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'failed');

        Http::assertNothingSent();
        $this->assertSame(OrderStatus::PendingPayment, $order->fresh()->status);
    }

    public function test_missing_email_prevents_snippe_post(): void
    {
        Http::fake();

        $user = User::factory()->create([
            'first_name' => 'Jane',
            'last_name' => 'Buyer',
            'name' => 'Jane Buyer',
            'email' => '',
        ]);
        $order = $this->createPayableOrder($user, ['total' => 45000]);

        Sanctum::actingAs($user);

        $this->postJson("/api/v1/payments/start/{$order->id}", [
            'provider' => PaymentMethod::Snippe->value,
            'phone_number' => '0712345678',
        ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'failed');

        Http::assertNothingSent();
        $this->assertSame(OrderStatus::PendingPayment, $order->fresh()->status);
    }

    public function test_malformed_email_prevents_snippe_post(): void
    {
        Http::fake();

        $user = User::factory()->create([
            'first_name' => 'Jane',
            'last_name' => 'Buyer',
            'name' => 'Jane Buyer',
            'email' => 'not-an-email',
        ]);
        $order = $this->createPayableOrder($user, ['total' => 45000]);

        Sanctum::actingAs($user);

        $this->postJson("/api/v1/payments/start/{$order->id}", [
            'provider' => PaymentMethod::Snippe->value,
            'phone_number' => '0712345678',
        ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'failed');

        Http::assertNothingSent();
    }

    public function test_valid_customer_identity_is_sent_to_snippe_without_placeholders(): void
    {
        Http::fake([
            'api.snippe.test/v1/payments' => Http::response([
                'status' => 'success',
                'code' => 201,
                'data' => ['reference' => 'pi_identity_ok', 'status' => 'pending'],
            ], 201),
        ]);

        $user = User::factory()->create([
            'first_name' => '  Jane  ',
            'last_name' => '  Buyer  ',
            'name' => 'Jane Buyer',
            'email' => '  Jane.Buyer@Example.COM  ',
        ]);
        $order = $this->createPayableOrder($user, ['total' => 45000]);

        Sanctum::actingAs($user);

        $this->postJson("/api/v1/payments/start/{$order->id}", [
            'provider' => PaymentMethod::Snippe->value,
            'phone_number' => '0712345678',
        ])->assertCreated();

        Http::assertSent(function ($request) {
            $body = $request->data();

            $this->assertSame('Jane', $body['customer']['firstname'] ?? null);
            $this->assertSame('Buyer', $body['customer']['lastname'] ?? null);
            $this->assertSame('jane.buyer@example.com', $body['customer']['email'] ?? null);
            $this->assertStringNotContainsString('Customer', json_encode($body['customer'] ?? []));
            $this->assertStringNotContainsString('customer@chinaorder.tz', json_encode($body['customer'] ?? []));

            return true;
        });
    }

    public function test_initiation_fails_closed_without_webhook_url(): void
    {
        config(['payments.snippe.webhook_url' => '']);

        $user = User::factory()->create();
        $order = $this->createPayableOrder($user, ['total' => 45000]);

        Sanctum::actingAs($user);
        Http::fake();

        $this->postJson("/api/v1/payments/start/{$order->id}", [
            'provider' => PaymentMethod::Snippe->value,
            'phone_number' => '0712345678',
        ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'failed');

        Http::assertNothingSent();
    }

    public function test_new_initiation_is_rejected_when_snippe_is_admin_disabled(): void
    {
        app(SettingsService::class)->set('payments.enabled_methods', [
            'nmb' => true,
            'snippe' => false,
            'mpesa' => false,
            'card' => false,
            'cash' => false,
            'bank_transfer' => false,
        ]);
        Cache::flush();

        $user = User::factory()->create();
        $order = $this->createPayableOrder($user, ['total' => 45000]);

        Sanctum::actingAs($user);
        Http::fake();

        $this->postJson("/api/v1/payments/start/{$order->id}", [
            'provider' => PaymentMethod::Snippe->value,
            'phone_number' => '0712345678',
        ])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'payment_failed');

        Http::assertNothingSent();
    }

    public function test_existing_transaction_can_refresh_after_env_disable(): void
    {
        $started = $this->startSnippeTransaction('pi_env_disable_refresh');

        config(['payments.snippe.enabled' => false]);

        Http::fake([
            'api.snippe.test/v1/payments/pi_env_disable_refresh' => Http::response([
                'status' => 'success',
                'code' => 200,
                'data' => [
                    'reference' => 'pi_env_disable_refresh',
                    'status' => 'completed',
                    'amount' => ['value' => 45000, 'currency' => 'TZS'],
                    'metadata' => [
                        'merchant_reference' => $started['merchant_reference'],
                        'payment_transaction_id' => $started['id'],
                    ],
                ],
            ]),
        ]);

        $this->postJson("/api/v1/payments/{$started['id']}/refresh")
            ->assertOk()
            ->assertJsonPath('data.status', 'successful');
    }

    public function test_existing_transaction_can_refresh_after_admin_disable(): void
    {
        $started = $this->startSnippeTransaction('pi_admin_disable_refresh');

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
            'api.snippe.test/v1/payments/pi_admin_disable_refresh' => Http::response([
                'status' => 'success',
                'code' => 200,
                'data' => [
                    'reference' => 'pi_admin_disable_refresh',
                    'external_reference' => 'S20388368013',
                    'status' => 'completed',
                    'amount' => ['value' => 45000, 'currency' => 'TZS'],
                    'metadata' => [
                        'merchant_reference' => $started['merchant_reference'],
                        'payment_transaction_id' => $started['id'],
                    ],
                ],
            ]),
        ]);

        $this->postJson("/api/v1/payments/{$started['id']}/refresh")
            ->assertOk()
            ->assertJsonPath('data.status', 'successful');
    }

    public function test_shipping_address_snapshot_is_used_for_snippe_customer_identity(): void
    {
        Http::fake([
            'api.snippe.test/v1/payments' => Http::response([
                'status' => 'success',
                'code' => 201,
                'data' => ['reference' => 'pi_snapshot_identity', 'status' => 'pending'],
            ], 201),
        ]);

        $user = User::factory()->create([
            'first_name' => 'Robert',
            'last_name' => 'Musa',
            'name' => 'Robert Musa',
            'email' => 'robert@example.com',
        ]);
        $order = $this->createPayableOrder($user, ['total' => 45000]);

        ShippingAddress::factory()->create([
            'order_id' => $order->id,
            'user_id' => $user->id,
            'first_name' => 'Snapshot',
            'last_name' => 'Recipient',
            'email' => 'snapshot.recipient@example.com',
        ]);

        Sanctum::actingAs($user);

        $this->postJson("/api/v1/payments/start/{$order->id}", [
            'provider' => PaymentMethod::Snippe->value,
            'phone_number' => '0712345678',
        ])->assertCreated();

        Http::assertSent(function ($request) {
            $body = $request->data();

            $this->assertSame('Snapshot', $body['customer']['firstname'] ?? null);
            $this->assertSame('Recipient', $body['customer']['lastname'] ?? null);
            $this->assertSame('snapshot.recipient@example.com', $body['customer']['email'] ?? null);

            return true;
        });
    }

    public function test_create_response_without_data_id_is_valid(): void
    {
        $expiresAt = now()->addHours(4)->toIso8601String();

        Http::fake([
            'api.snippe.test/v1/payments' => Http::response([
                'status' => 'success',
                'code' => 201,
                'data' => [
                    'reference' => 'pi_no_id',
                    'status' => 'pending',
                    'expires_at' => $expiresAt,
                    'amount' => ['value' => 45000, 'currency' => 'TZS'],
                ],
            ], 201),
        ]);

        $user = User::factory()->create([
            'first_name' => 'Jane',
            'last_name' => 'Buyer',
            'email' => 'jane@example.com',
        ]);
        $order = $this->createPayableOrder($user, ['total' => 45000]);

        Sanctum::actingAs($user);

        $response = $this->postJson("/api/v1/payments/start/{$order->id}", [
            'provider' => PaymentMethod::Snippe->value,
            'phone_number' => '0712345678',
        ])->assertCreated();

        $this->assertSame('pi_no_id', $response->json('data.provider_reference'));
        $this->assertNull($response->json('data.external_transaction_id'));
        $this->assertSame($expiresAt, $response->json('data.response_payload.data.expires_at'));
    }

    /**
     * @return array{id: string, merchant_reference: string}
     */
    private function startSnippeTransaction(string $reference = 'pi_wrong_amount'): array
    {
        Http::fake([
            'api.snippe.test/v1/payments' => Http::response([
                'status' => 'success',
                'code' => 201,
                'data' => ['reference' => $reference, 'status' => 'pending'],
            ], 201),
        ]);

        $user = User::factory()->create([
            'first_name' => 'Jane',
            'last_name' => 'Buyer',
            'email' => 'jane@example.com',
        ]);
        $order = $this->createPayableOrder($user, ['total' => 45000]);

        Sanctum::actingAs($user);

        $response = $this->postJson("/api/v1/payments/start/{$order->id}", [
            'provider' => PaymentMethod::Snippe->value,
            'phone_number' => '0712345678',
        ])->assertCreated();

        return [
            'id' => $response->json('data.id'),
            'merchant_reference' => $response->json('data.merchant_reference'),
        ];
    }

    private function configureNmb(): void
    {
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
        ]);

        Http::fake([
            'sandbox.nmb.test/*' => Http::response([
                'result' => 'SUCCESS',
                'session' => [
                    'id' => 'SESSION-SNIPPE-SUITE',
                    'successIndicator' => 'indicator-snippe-suite',
                    'checkoutUrl' => 'https://checkout.nmb.test/pay/snippe-suite',
                ],
            ]),
        ]);
    }

    private function configureSnippe(): void
    {
        config([
            'payments.snippe.enabled' => true,
            'payments.snippe.base_url' => 'https://api.snippe.test',
            'payments.snippe.api_key' => 'snippe-test-api-key',
            'payments.snippe.webhook_secret' => 'whsec_test_placeholder',
            'payments.snippe.webhook_url' => 'https://example.test/api/v1/payments/snippe/webhook',
            'payments.snippe.http_timeout' => 5,
            'payments.snippe.http_connect_timeout' => 2,
            'payments.snippe.http_retry_times' => 0,
        ]);
    }

    private function enableSnippeInSettings(): void
    {
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
}
