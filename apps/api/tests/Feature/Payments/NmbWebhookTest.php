<?php

namespace Tests\Feature\Payments;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NmbWebhookTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.nmb.auto_verify_after_callback' => false,
            'services.nmb.auto_complete_after_verification' => false,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validCallbackPayload(string $sessionId = 'SESSION000123', string $reference = 'PAY-2026-000001'): array
    {
        return [
            'result' => 'SUCCESS',
            'session' => [
                'id' => $sessionId,
                'successIndicator' => 'indicator-123',
            ],
            'order' => [
                'id' => $reference,
                'amount' => '75000.00',
                'currency' => 'TZS',
            ],
        ];
    }

    public function test_webhook_accepts_callback(): void
    {
        $response = $this->postJson('/api/v1/webhooks/nmb', $this->validCallbackPayload());

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.accepted', true)
            ->assertJsonPath('data.message', 'NMB callback received. Payment not matched.');
    }

    public function test_webhook_does_not_require_authentication(): void
    {
        $this->postJson('/api/v1/webhooks/nmb', $this->validCallbackPayload())
            ->assertOk()
            ->assertJsonPath('data.accepted', true);
    }

    public function test_callback_stored_on_matched_payment(): void
    {
        $payment = Payment::factory()->nmb()->initiated()->create([
            'reference' => 'PAY-2026-000001',
            'gateway_session_id' => 'SESSION000123',
            'status' => PaymentStatus::Initiated,
        ]);

        $response = $this->postJson('/api/v1/webhooks/nmb', $this->validCallbackPayload());

        $response->assertOk()
            ->assertJsonPath('data.accepted', true)
            ->assertJsonPath('data.payment_id', $payment->id)
            ->assertJsonPath('data.message', 'NMB callback received and stored.');

        $payment->refresh();

        $this->assertCount(1, $payment->metadata['nmb_callbacks'] ?? []);
        $this->assertSame('SESSION000123', $payment->metadata['nmb_callbacks'][0]['session_id']);
        $this->assertSame('PAY-2026-000001', $payment->metadata['nmb_callbacks'][0]['order_reference']);
        $this->assertSame('SUCCESS', $payment->metadata['nmb_callbacks'][0]['result']);
    }

    public function test_payment_is_not_marked_paid(): void
    {
        $payment = Payment::factory()->nmb()->initiated()->create([
            'reference' => 'PAY-2026-000002',
            'gateway_session_id' => 'SESSION000456',
        ]);

        $this->postJson('/api/v1/webhooks/nmb', $this->validCallbackPayload('SESSION000456', 'PAY-2026-000002'))
            ->assertOk();

        $payment->refresh();

        $this->assertSame(PaymentStatus::Initiated, $payment->status);
        $this->assertNull($payment->paid_at);
    }

    public function test_callback_stored_safely_without_secrets(): void
    {
        $payment = Payment::factory()->nmb()->initiated()->create([
            'reference' => 'PAY-2026-000999',
            'gateway_session_id' => 'SESSION000999',
        ]);

        $this->postJson('/api/v1/webhooks/nmb', [
            'result' => 'SUCCESS',
            'session' => ['id' => 'SESSION000999'],
            'password' => 'secret-value',
            'order' => ['id' => 'PAY-2026-000999'],
        ])->assertOk();

        $payment->refresh();

        $storedPayload = $payment->metadata['nmb_callbacks'][0]['payload'] ?? [];
        $this->assertSame('[REDACTED]', $storedPayload['password'] ?? null);
    }

    public function test_invalid_payload_rejected(): void
    {
        $this->postJson('/api/v1/webhooks/nmb', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['callback']);
    }

    public function test_payment_matched_by_order_reference(): void
    {
        $payment = Payment::factory()->nmb()->initiated()->create([
            'reference' => 'PAY-2026-000777',
            'gateway_session_id' => null,
        ]);

        $this->postJson('/api/v1/webhooks/nmb', [
            'result' => 'PENDING',
            'order' => [
                'id' => 'PAY-2026-000777',
            ],
        ])->assertOk()
            ->assertJsonPath('data.payment_id', $payment->id);

        $payment->refresh();
        $this->assertCount(1, $payment->metadata['nmb_callbacks'] ?? []);
    }
}
