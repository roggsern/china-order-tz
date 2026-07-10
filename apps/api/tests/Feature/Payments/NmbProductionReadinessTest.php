<?php

namespace Tests\Feature\Payments;

use App\Enums\PaymentStatus;
use App\Jobs\Payments\ProcessNmbCallbackJob;
use App\Models\Payment;
use App\Services\Payments\NmbVerificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class NmbProductionReadinessTest extends TestCase
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
            'services.nmb.password' => 'sandbox-password',
            'services.nmb.return_url' => 'https://app.chinaorder.test/payments/return',
            'services.nmb.callback_url' => 'https://app.chinaorder.test/webhooks/nmb',
            'services.nmb.merchant_name' => 'China Order TZ',
            'services.nmb.merchant_url' => 'https://chinaorder.test',
            'services.nmb.auto_verify_after_callback' => true,
            'services.nmb.auto_complete_after_verification' => false,
            'services.nmb.process_callbacks_async' => true,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function callbackPayload(string $sessionId, string $reference): array
    {
        return [
            'result' => 'SUCCESS',
            'session' => ['id' => $sessionId],
            'order' => ['id' => $reference],
        ];
    }

    public function test_webhook_dispatches_async_processing_job(): void
    {
        Bus::fake([ProcessNmbCallbackJob::class]);
        Cache::flush();

        $payment = Payment::factory()->nmb()->initiated()->create([
            'reference' => 'PAY-2026-000111',
            'gateway_session_id' => 'SESSION000111',
        ]);

        $this->postJson('/api/v1/webhooks/nmb', $this->callbackPayload('SESSION000111', 'PAY-2026-000111'))
            ->assertOk()
            ->assertJsonPath('data.accepted', true)
            ->assertJsonPath('data.payment_id', $payment->id);

        Bus::assertDispatched(ProcessNmbCallbackJob::class, function (ProcessNmbCallbackJob $job) use ($payment) {
            return $job->paymentId === $payment->id;
        });
    }

    public function test_replay_callback_is_acknowledged_without_duplicate_storage(): void
    {
        Cache::flush();

        $payment = Payment::factory()->nmb()->initiated()->create([
            'reference' => 'PAY-2026-000222',
            'gateway_session_id' => 'SESSION000222',
        ]);

        $payload = $this->callbackPayload('SESSION000222', 'PAY-2026-000222');

        config(['services.nmb.auto_verify_after_callback' => false]);

        $this->postJson('/api/v1/webhooks/nmb', $payload)->assertOk();
        $this->postJson('/api/v1/webhooks/nmb', $payload)
            ->assertOk()
            ->assertJsonPath('data.message', 'NMB callback already processed.');

        $payment->refresh();

        $this->assertCount(1, $payment->metadata['nmb_callbacks'] ?? []);
    }

    public function test_transient_verification_failure_keeps_payment_initiated(): void
    {
        Http::fake([
            'sandbox.nmb.test/*' => Http::response(['error' => ['explanation' => 'Unavailable']], 503),
        ]);

        $payment = Payment::factory()->nmb()->initiated()->create([
            'reference' => 'PAY-2026-000333',
            'amount' => 75000,
            'currency' => 'TZS',
        ]);

        $result = app(NmbVerificationService::class)->verify($payment);

        $this->assertFalse($result->verified);
        $this->assertTrue($result->transientFailure);

        $payment->refresh();

        $this->assertSame(PaymentStatus::Initiated, $payment->status);
        $this->assertTrue($payment->metadata['nmb_verification']['transient_failure'] ?? false);
    }

    public function test_reconcile_command_retries_verification(): void
    {
        Http::fake([
            'sandbox.nmb.test/*' => Http::response([
                'result' => 'SUCCESS',
                'order' => [
                    'id' => 'PAY-2026-000444',
                    'amount' => '75000.00',
                    'currency' => 'TZS',
                ],
                'transaction' => ['id' => 'TRANS000444'],
            ]),
        ]);

        $payment = Payment::factory()->nmb()->initiated()->create([
            'reference' => 'PAY-2026-000444',
            'amount' => 75000,
            'currency' => 'TZS',
        ]);

        $this->artisan('nmb:reconcile-payments', ['--limit' => 10])
            ->assertSuccessful();

        $payment->refresh();

        $this->assertTrue($payment->metadata['nmb_verification']['verified'] ?? false);
        $this->assertSame(PaymentStatus::Initiated, $payment->status);
    }

    public function test_validate_config_command_reports_errors(): void
    {
        config([
            'services.nmb.enabled' => true,
            'services.nmb.base_url' => null,
        ]);

        $this->artisan('nmb:validate-config')
            ->assertFailed();
    }
}
