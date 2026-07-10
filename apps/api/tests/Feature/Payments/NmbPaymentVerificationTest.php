<?php

namespace Tests\Feature\Payments;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Services\Payments\NmbVerificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class NmbPaymentVerificationTest extends TestCase
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
            'services.nmb.auto_verify_after_callback' => false,
            'services.nmb.auto_complete_after_verification' => false,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function fakeRetrieveOrderSuccess(string $reference = 'PAY-2026-000123', string $transactionId = 'TRANS000123'): array
    {
        $response = [
            'result' => 'SUCCESS',
            'order' => [
                'id' => $reference,
                'amount' => '75000.00',
                'currency' => 'TZS',
            ],
            'transaction' => [
                'id' => $transactionId,
            ],
        ];

        Http::fake([
            'sandbox.nmb.test/*' => Http::response($response),
        ]);

        return $response;
    }

    public function test_verification_retrieves_order_from_mpgs(): void
    {
        $this->fakeRetrieveOrderSuccess();

        $payment = Payment::factory()->nmb()->initiated()->create([
            'reference' => 'PAY-2026-000123',
            'amount' => 75000,
            'currency' => 'TZS',
        ]);

        $result = app(NmbVerificationService::class)->verify($payment);

        $this->assertTrue($result->verified);
        $this->assertSame('TRANS000123', $result->transactionId);

        Http::assertSent(function ($request) {
            return $request->method() === 'GET'
                && str_contains($request->url(), '/merchant/TESTMERCHANT/order/PAY-2026-000123');
        });
    }

    public function test_verification_persists_metadata_and_gateway_response(): void
    {
        $this->fakeRetrieveOrderSuccess();

        $payment = Payment::factory()->nmb()->initiated()->create([
            'reference' => 'PAY-2026-000123',
            'amount' => 75000,
            'currency' => 'TZS',
            'gateway_response' => [
                'result' => 'SUCCESS',
                'session' => ['id' => 'SESSION000123'],
            ],
        ]);

        app(NmbVerificationService::class)->verify($payment);

        $payment->refresh();

        $this->assertTrue($payment->metadata['nmb_verification']['verified'] ?? false);
        $this->assertSame('TRANS000123', $payment->metadata['nmb_verification']['transaction_id'] ?? null);
        $this->assertSame('SUCCESS', $payment->gateway_response['result'] ?? null);
        $this->assertSame('SESSION000123', $payment->gateway_response['session']['id'] ?? null);
        $this->assertSame('SUCCESS', $payment->gateway_response['verification']['result'] ?? null);
    }

    public function test_verification_does_not_mark_payment_paid(): void
    {
        $this->fakeRetrieveOrderSuccess();

        $payment = Payment::factory()->nmb()->initiated()->create([
            'reference' => 'PAY-2026-000123',
            'amount' => 75000,
            'currency' => 'TZS',
        ]);

        app(NmbVerificationService::class)->verify($payment);

        $payment->refresh();

        $this->assertSame(PaymentStatus::Initiated, $payment->status);
        $this->assertNull($payment->paid_at);
    }

    public function test_verification_does_not_update_order(): void
    {
        $this->fakeRetrieveOrderSuccess();

        $order = Order::factory()->create([
            'status' => OrderStatus::Pending,
            'paid_at' => null,
        ]);

        $payment = Payment::factory()->nmb()->initiated()->create([
            'order_id' => $order->id,
            'reference' => 'PAY-2026-000123',
            'amount' => 75000,
            'currency' => 'TZS',
        ]);

        app(NmbVerificationService::class)->verify($payment);

        $order->refresh();

        $this->assertSame(OrderStatus::Pending, $order->status);
        $this->assertNull($order->paid_at);
    }

    public function test_failed_verification_marks_payment_failed(): void
    {
        Http::fake([
            'sandbox.nmb.test/*' => Http::response([
                'result' => 'FAILURE',
                'error' => ['explanation' => 'Order not found.'],
            ]),
        ]);

        $payment = Payment::factory()->nmb()->initiated()->create([
            'reference' => 'PAY-2026-000123',
            'amount' => 75000,
            'currency' => 'TZS',
        ]);

        $result = app(NmbVerificationService::class)->verify($payment);

        $this->assertFalse($result->verified);

        $payment->refresh();

        $this->assertSame(PaymentStatus::Failed, $payment->status);
        $this->assertFalse($payment->metadata['nmb_verification']['verified'] ?? true);
    }

    public function test_webhook_triggers_verification_when_enabled(): void
    {
        config([
            'services.nmb.auto_verify_after_callback' => true,
            'services.nmb.auto_complete_after_verification' => false,
        ]);

        $this->fakeRetrieveOrderSuccess('PAY-2026-000456', 'TRANS000456');

        $payment = Payment::factory()->nmb()->initiated()->create([
            'reference' => 'PAY-2026-000456',
            'gateway_session_id' => 'SESSION000456',
            'amount' => 75000,
            'currency' => 'TZS',
        ]);

        $this->postJson('/api/v1/webhooks/nmb', [
            'result' => 'SUCCESS',
            'session' => ['id' => 'SESSION000456'],
            'order' => ['id' => 'PAY-2026-000456'],
        ])->assertOk();

        $payment->refresh();

        $this->assertTrue($payment->metadata['nmb_verification']['verified'] ?? false);
        $this->assertSame(PaymentStatus::Initiated, $payment->status);
    }

    public function test_verification_is_idempotent_when_already_verified(): void
    {
        Http::fake();

        $payment = Payment::factory()->nmb()->initiated()->create([
            'reference' => 'PAY-2026-000789',
            'metadata' => [
                'nmb_verification' => [
                    'verified' => true,
                    'message' => 'NMB transaction already verified.',
                    'result' => 'SUCCESS',
                    'order_id' => 'PAY-2026-000789',
                    'transaction_id' => 'TRANS000789',
                ],
            ],
        ]);

        $result = app(NmbVerificationService::class)->verify($payment);

        $this->assertTrue($result->verified);
        Http::assertNothingSent();
    }
}
