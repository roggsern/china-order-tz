<?php

namespace Tests\Feature\Payments;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Services\Payments\NmbPaymentCompletionService;
use App\Services\Payments\NmbVerificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class NmbPaymentCompletionTest extends TestCase
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
            'services.nmb.auto_complete_after_verification' => true,
        ]);
    }

    private function fakeRetrieveOrderSuccess(string $reference, float $amount = 75000): void
    {
        Http::fake([
            'sandbox.nmb.test/*' => Http::response([
                'result' => 'SUCCESS',
                'order' => [
                    'id' => $reference,
                    'amount' => number_format($amount, 2, '.', ''),
                    'currency' => 'TZS',
                ],
                'transaction' => [
                    'id' => 'TRANS000123',
                ],
            ]),
        ]);
    }

    private function createVerifiedPayment(array $paymentOverrides = [], array $orderOverrides = []): Payment
    {
        $order = Order::factory()->pending()->create(array_merge([
            'total' => 75000,
            'currency' => 'TZS',
            'paid_at' => null,
        ], $orderOverrides));

        return Payment::factory()->nmb()->initiated()->create(array_merge([
            'order_id' => $order->id,
            'user_id' => $order->user_id,
            'reference' => 'PAY-2026-000123',
            'amount' => 75000,
            'currency' => 'TZS',
            'metadata' => [
                'nmb_verification' => [
                    'verified' => true,
                    'verified_at' => now()->toIso8601String(),
                    'result' => 'SUCCESS',
                    'transaction_id' => 'TRANS000123',
                ],
            ],
        ], $paymentOverrides));
    }

    public function test_completion_marks_payment_and_order_paid(): void
    {
        $payment = $this->createVerifiedPayment();

        $result = app(NmbPaymentCompletionService::class)->complete($payment);

        $this->assertTrue($result->completed);
        $this->assertFalse($result->alreadyCompleted);

        $payment->refresh();
        $order = $payment->order()->firstOrFail();

        $this->assertSame(PaymentStatus::Paid, $payment->status);
        $this->assertNotNull($payment->paid_at);
        $this->assertSame(OrderStatus::Paid, $order->status);
        $this->assertNotNull($order->paid_at);
        $this->assertNotNull($payment->metadata['nmb_completion']['completed_at'] ?? null);
        $this->assertSame('verification', $payment->metadata['nmb_completion']['source'] ?? null);
        $this->assertDatabaseHas('fulfillments', ['order_id' => $order->id]);
        $this->assertDatabaseHas('warehouse_jobs', [
            'fulfillment_id' => \App\Models\Fulfillment::query()->where('order_id', $order->id)->value('id'),
        ]);
    }

    public function test_verification_triggers_completion_when_enabled(): void
    {
        config(['services.nmb.auto_complete_after_verification' => true]);
        $this->fakeRetrieveOrderSuccess('PAY-2026-000456');

        $order = Order::factory()->pending()->create([
            'total' => 75000,
            'currency' => 'TZS',
        ]);

        $payment = Payment::factory()->nmb()->initiated()->create([
            'order_id' => $order->id,
            'user_id' => $order->user_id,
            'reference' => 'PAY-2026-000456',
            'amount' => 75000,
            'currency' => 'TZS',
        ]);

        app(NmbVerificationService::class)->verify($payment);

        $payment->refresh();
        $order->refresh();

        $this->assertSame(PaymentStatus::Paid, $payment->status);
        $this->assertSame(OrderStatus::Paid, $order->status);
    }

    public function test_verification_does_not_complete_when_disabled(): void
    {
        config(['services.nmb.auto_complete_after_verification' => false]);
        $this->fakeRetrieveOrderSuccess('PAY-2026-000789');

        $order = Order::factory()->pending()->create([
            'total' => 75000,
            'currency' => 'TZS',
        ]);

        $payment = Payment::factory()->nmb()->initiated()->create([
            'order_id' => $order->id,
            'user_id' => $order->user_id,
            'reference' => 'PAY-2026-000789',
            'amount' => 75000,
            'currency' => 'TZS',
        ]);

        app(NmbVerificationService::class)->verify($payment);

        $payment->refresh();
        $order->refresh();

        $this->assertSame(PaymentStatus::Initiated, $payment->status);
        $this->assertSame(OrderStatus::Pending, $order->status);
    }

    public function test_completion_is_idempotent(): void
    {
        $payment = $this->createVerifiedPayment([
            'status' => PaymentStatus::Paid,
            'paid_at' => now(),
            'metadata' => [
                'nmb_verification' => [
                    'verified' => true,
                    'transaction_id' => 'TRANS000123',
                ],
                'nmb_completion' => [
                    'completed_at' => now()->toIso8601String(),
                    'source' => 'verification',
                ],
            ],
        ]);

        $result = app(NmbPaymentCompletionService::class)->complete($payment);

        $this->assertTrue($result->completed);
        $this->assertTrue($result->alreadyCompleted);
    }

    public function test_failed_verification_does_not_complete(): void
    {
        config(['services.nmb.auto_complete_after_verification' => true]);

        Http::fake([
            'sandbox.nmb.test/*' => Http::response([
                'result' => 'FAILURE',
                'error' => ['explanation' => 'Order not found.'],
            ]),
        ]);

        $order = Order::factory()->pending()->create([
            'total' => 75000,
            'currency' => 'TZS',
        ]);

        $payment = Payment::factory()->nmb()->initiated()->create([
            'order_id' => $order->id,
            'reference' => 'PAY-2026-000321',
            'amount' => 75000,
            'currency' => 'TZS',
        ]);

        app(NmbVerificationService::class)->verify($payment);

        $payment->refresh();
        $order->refresh();

        $this->assertSame(PaymentStatus::Failed, $payment->status);
        $this->assertSame(OrderStatus::Pending, $order->status);
        $this->assertNull($payment->metadata['nmb_completion']['completed_at'] ?? null);
    }

    public function test_webhook_verify_and_complete_flow(): void
    {
        $this->fakeRetrieveOrderSuccess('PAY-2026-000555');

        $order = Order::factory()->pending()->create([
            'total' => 75000,
            'currency' => 'TZS',
        ]);

        $payment = Payment::factory()->nmb()->initiated()->create([
            'order_id' => $order->id,
            'user_id' => $order->user_id,
            'reference' => 'PAY-2026-000555',
            'gateway_session_id' => 'SESSION000555',
            'amount' => 75000,
            'currency' => 'TZS',
        ]);

        $this->postJson('/api/v1/webhooks/nmb', [
            'result' => 'SUCCESS',
            'session' => ['id' => 'SESSION000555'],
            'order' => ['id' => 'PAY-2026-000555'],
        ])->assertOk();

        $payment->refresh();
        $order->refresh();

        $this->assertTrue($payment->metadata['nmb_verification']['verified'] ?? false);
        $this->assertSame(PaymentStatus::Paid, $payment->status);
        $this->assertSame(OrderStatus::Paid, $order->status);
    }

    public function test_completion_rejects_unverified_payment(): void
    {
        $order = Order::factory()->pending()->create([
            'total' => 75000,
            'currency' => 'TZS',
        ]);

        $payment = Payment::factory()->nmb()->initiated()->create([
            'order_id' => $order->id,
            'reference' => 'PAY-2026-000888',
            'amount' => 75000,
            'currency' => 'TZS',
        ]);

        $this->expectException(\Illuminate\Validation\ValidationException::class);

        app(NmbPaymentCompletionService::class)->complete($payment);
    }

    public function test_cached_verification_still_completes_on_retry(): void
    {
        config(['services.nmb.auto_complete_after_verification' => true]);
        Http::fake();

        $order = Order::factory()->pending()->create([
            'total' => 75000,
            'currency' => 'TZS',
        ]);

        $payment = Payment::factory()->nmb()->initiated()->create([
            'order_id' => $order->id,
            'user_id' => $order->user_id,
            'reference' => 'PAY-2026-000777',
            'amount' => 75000,
            'currency' => 'TZS',
            'metadata' => [
                'nmb_verification' => [
                    'verified' => true,
                    'verified_at' => now()->toIso8601String(),
                    'result' => 'SUCCESS',
                    'transaction_id' => 'TRANS000777',
                ],
            ],
        ]);

        app(NmbVerificationService::class)->verify($payment);

        $payment->refresh();
        $order->refresh();

        $this->assertSame(PaymentStatus::Paid, $payment->status);
        $this->assertSame(OrderStatus::Paid, $order->status);
        Http::assertNothingSent();
    }
}
