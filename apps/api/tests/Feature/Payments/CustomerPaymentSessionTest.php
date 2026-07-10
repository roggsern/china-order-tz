<?php

namespace Tests\Feature\Payments;

use App\Enums\PaymentStatus;
use App\Models\Admin;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CustomerPaymentSessionTest extends TestCase
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
    }

    private function fakeSandboxSuccess(string $sessionId = 'SESSION000123'): void
    {
        Http::fake([
            'sandbox.nmb.test/*' => Http::response([
                'result' => 'SUCCESS',
                'session' => [
                    'id' => $sessionId,
                    'successIndicator' => 'indicator-123',
                ],
            ]),
        ]);
    }

    private function createPaymentForUser(User $user, array $overrides = []): Payment
    {
        return Payment::factory()->nmb()->create(array_merge([
            'user_id' => $user->id,
            'status' => PaymentStatus::Pending,
            'amount' => 75000,
            'currency' => 'TZS',
            'reference' => 'PAY-2026-000001',
        ], $overrides));
    }

    public function test_payment_initiation(): void
    {
        $this->fakeSandboxSuccess();

        $user = User::factory()->create();
        $payment = $this->createPaymentForUser($user);

        Sanctum::actingAs($user);

        $this->postJson("/api/v1/payments/{$payment->id}/initiate")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.payment_id', $payment->id)
            ->assertJsonPath('data.reference', 'PAY-2026-000001')
            ->assertJsonPath('data.status', PaymentStatus::Initiated->value)
            ->assertJsonPath('data.gateway_session_id', 'SESSION000123');
    }

    public function test_gateway_invoked(): void
    {
        $this->fakeSandboxSuccess('SESSION000456');

        $user = User::factory()->create();
        $payment = $this->createPaymentForUser($user);

        Sanctum::actingAs($user);

        $this->postJson("/api/v1/payments/{$payment->id}/initiate")->assertOk();

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/api/rest/version/85/merchant/TESTMERCHANT/session')
                && ($request->data()['apiOperation'] ?? null) === 'INITIATE_CHECKOUT';
        });
    }

    public function test_gateway_session_persistence(): void
    {
        $this->fakeSandboxSuccess('SESSION000789');

        $user = User::factory()->create();
        $payment = $this->createPaymentForUser($user);

        Sanctum::actingAs($user);

        $this->postJson("/api/v1/payments/{$payment->id}/initiate")->assertOk();

        $payment->refresh();

        $this->assertSame('SESSION000789', $payment->gateway_session_id);
        $this->assertSame('indicator-123', $payment->success_indicator);
        $this->assertSame('SESSION000789', $payment->gateway_reference);
        $this->assertNull($payment->checkout_url);
        $this->assertSame('SUCCESS', $payment->gateway_response['result'] ?? null);
    }

    public function test_payment_marked_initiated(): void
    {
        $this->fakeSandboxSuccess();

        $user = User::factory()->create();
        $payment = $this->createPaymentForUser($user);

        Sanctum::actingAs($user);

        $this->postJson("/api/v1/payments/{$payment->id}/initiate")->assertOk();

        $payment->refresh();

        $this->assertSame(PaymentStatus::Initiated, $payment->status);
        $this->assertNotNull($payment->initiated_at);
    }

    public function test_checkout_url_not_generated_without_gateway_value(): void
    {
        $this->fakeSandboxSuccess('SESSION000321');

        $user = User::factory()->create();
        $payment = $this->createPaymentForUser($user);

        Sanctum::actingAs($user);

        $this->postJson("/api/v1/payments/{$payment->id}/initiate")
            ->assertOk()
            ->assertJsonPath('data.checkout_url', null);
    }

    public function test_ownership_validation(): void
    {
        $this->fakeSandboxSuccess();

        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $payment = $this->createPaymentForUser($owner);

        Sanctum::actingAs($otherUser);

        $this->postJson("/api/v1/payments/{$payment->id}/initiate")->assertNotFound();
    }

    public function test_paid_payment_rejected(): void
    {
        $user = User::factory()->create();
        $payment = $this->createPaymentForUser($user, [
            'status' => PaymentStatus::Paid,
            'paid_at' => now(),
        ]);

        Sanctum::actingAs($user);

        $this->postJson("/api/v1/payments/{$payment->id}/initiate")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['payment']);
    }

    public function test_expired_payment_rejected(): void
    {
        $user = User::factory()->create();
        $payment = $this->createPaymentForUser($user, [
            'status' => PaymentStatus::Expired,
        ]);

        Sanctum::actingAs($user);

        $this->postJson("/api/v1/payments/{$payment->id}/initiate")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['payment']);
    }

    public function test_cancelled_payment_rejected(): void
    {
        $user = User::factory()->create();
        $payment = $this->createPaymentForUser($user, [
            'status' => PaymentStatus::Cancelled,
        ]);

        Sanctum::actingAs($user);

        $this->postJson("/api/v1/payments/{$payment->id}/initiate")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['payment']);
    }

    public function test_guest_rejected(): void
    {
        $payment = Payment::factory()->nmb()->create();

        $this->postJson("/api/v1/payments/{$payment->id}/initiate")->assertUnauthorized();
    }

    public function test_admin_rejected(): void
    {
        Sanctum::actingAs(Admin::factory()->create());
        $payment = Payment::factory()->nmb()->create();

        $this->postJson("/api/v1/payments/{$payment->id}/initiate")->assertUnauthorized();
    }
}
