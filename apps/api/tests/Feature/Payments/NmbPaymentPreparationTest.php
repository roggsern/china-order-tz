<?php

namespace Tests\Feature\Payments;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Admin;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class NmbPaymentPreparationTest extends TestCase
{
    use RefreshDatabase;

    public function test_process_payment_rejects_nmb_method(): void
    {
        Sanctum::actingAs(Admin::factory()->create());

        $payment = Payment::factory()->nmb()->create();

        $response = $this->postJson("/api/v1/admin/payments/{$payment->id}/mock", [
            'result' => 'success',
        ]);

        $response->assertUnprocessable();
    }

    public function test_customer_can_initiate_nmb_payment(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $payment = Payment::factory()->nmb()->create([
            'user_id' => $user->id,
        ]);

        $response = $this->postJson("/api/v1/payments/{$payment->id}/initiate");

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', PaymentStatus::Initiated->value);

        $payment->refresh();
        $this->assertSame(PaymentStatus::Initiated, $payment->status);
        $this->assertNotNull($payment->transaction_id);
    }

    public function test_admin_can_simulate_nmb_callback_success(): void
    {
        Sanctum::actingAs(Admin::factory()->create());

        $payment = Payment::factory()->nmb()->initiated()->create();

        $response = $this->postJson("/api/v1/admin/payments/{$payment->id}/simulate-nmb-callback", [
            'result' => 'success',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Payment processed successfully.');

        $payment->refresh();
        $this->assertSame(PaymentStatus::Paid, $payment->status);
        $this->assertNotNull($payment->paid_at);
    }

    public function test_nmb_webhook_receiver_is_not_implemented_yet(): void
    {
        $response = $this->postJson('/api/v1/webhooks/payments/nmb');

        $response->assertStatus(501)
            ->assertJsonPath('data.accepted', false);
    }

    public function test_payment_service_supports_async_for_nmb_methods(): void
    {
        $payment = Payment::factory()->nmb()->create();

        $this->assertTrue(app(\App\Payments\Services\PaymentService::class)->supportsAsync($payment));

        $bankTransferPayment = Payment::factory()->create([
            'method' => PaymentMethod::BankTransfer,
        ]);

        $this->assertTrue(app(\App\Payments\Services\PaymentService::class)->supportsAsync($bankTransferPayment));
    }
}
