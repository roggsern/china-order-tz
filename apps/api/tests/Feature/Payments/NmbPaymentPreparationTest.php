<?php

namespace Tests\Feature\Payments;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Admin;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class NmbPaymentPreparationTest extends TestCase
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
        ]);

        Http::fake([
            'sandbox.nmb.test/*' => Http::response([
                'result' => 'SUCCESS',
                'session' => [
                    'id' => 'SESSION000010',
                    'successIndicator' => 'indicator-010',
                ],
            ]),
        ]);
    }

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
            'reference' => 'PAY-2026-000010',
        ]);

        $response = $this->postJson("/api/v1/payments/{$payment->id}/initiate");

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', PaymentStatus::Initiated->value)
            ->assertJsonPath('data.gateway_session_id', 'SESSION000010')
            ->assertJsonPath('data.checkout_url', null);

        $payment->refresh();
        $this->assertSame(PaymentStatus::Initiated, $payment->status);
        $this->assertSame('SESSION000010', $payment->gateway_session_id);
        $this->assertSame('indicator-010', $payment->success_indicator);
        $this->assertNull($payment->checkout_url);
        $this->assertNotNull($payment->initiated_at);
    }

    public function test_admin_nmb_callback_simulation_is_not_implemented_yet(): void
    {
        Sanctum::actingAs(Admin::factory()->create());

        $payment = Payment::factory()->nmb()->initiated()->create();

        $response = $this->postJson("/api/v1/admin/payments/{$payment->id}/simulate-nmb-callback", [
            'result' => 'success',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Payment failed.');

        $payment->refresh();
        $this->assertSame(PaymentStatus::Initiated, $payment->status);
        $this->assertNull($payment->paid_at);
    }

    public function test_nmb_webhook_accepts_callback(): void
    {
        $response = $this->postJson('/api/v1/webhooks/nmb', [
            'result' => 'SUCCESS',
            'session' => [
                'id' => 'SESSION000010',
            ],
            'order' => [
                'id' => 'PAY-2026-000010',
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.accepted', true);
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
