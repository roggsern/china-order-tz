<?php

namespace Tests\Feature\Payments;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Admin;
use App\Models\Payment;
use App\Models\User;
use App\Payments\Gateways\Nmb\NmbApiClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Mockery;
use Tests\TestCase;

class CustomerPaymentSessionTest extends TestCase
{
    use RefreshDatabase;

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
        $user = User::factory()->create();
        $payment = $this->createPaymentForUser($user);

        Sanctum::actingAs($user);

        $this->postJson("/api/v1/payments/{$payment->id}/initiate")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.payment_id', $payment->id)
            ->assertJsonPath('data.reference', 'PAY-2026-000001')
            ->assertJsonPath('data.status', PaymentStatus::Initiated->value)
            ->assertJsonPath('data.amount', '75000.00');
    }

    public function test_gateway_invoked(): void
    {
        $user = User::factory()->create();
        $payment = $this->createPaymentForUser($user);

        $mockClient = Mockery::mock(NmbApiClient::class);
        $mockClient->shouldReceive('createSession')
            ->once()
            ->with(Mockery::on(fn (Payment $subject) => $subject->is($payment)))
            ->andReturn(new \App\Payments\DTOs\InitiatePaymentResult(
                success: true,
                status: PaymentStatus::Initiated->value,
                message: 'Mock session',
                checkoutRequestId: 'gw-ref-123',
                gatewayReference: 'gw-ref-123',
                checkoutUrl: 'https://sandbox.nmb.co.tz/pay/mock/PAY-2026-000001',
                gatewayResponse: ['mode' => 'mock'],
            ));

        $this->app->instance(NmbApiClient::class, $mockClient);

        Sanctum::actingAs($user);

        $this->postJson("/api/v1/payments/{$payment->id}/initiate")
            ->assertOk()
            ->assertJsonPath('data.gateway_reference', 'gw-ref-123');
    }

    public function test_payment_marked_initiated_with_session_fields(): void
    {
        $user = User::factory()->create();
        $payment = $this->createPaymentForUser($user);

        Sanctum::actingAs($user);

        $this->postJson("/api/v1/payments/{$payment->id}/initiate")->assertOk();

        $payment->refresh();

        $this->assertSame(PaymentStatus::Initiated, $payment->status);
        $this->assertNotNull($payment->initiated_at);
        $this->assertNotNull($payment->gateway_reference);
        $this->assertNotNull($payment->checkout_url);
        $this->assertIsArray($payment->gateway_response);
        $this->assertSame('mock', $payment->gateway_response['mode'] ?? null);
    }

    public function test_checkout_url_returned(): void
    {
        $user = User::factory()->create();
        $payment = $this->createPaymentForUser($user);

        Sanctum::actingAs($user);

        $response = $this->postJson("/api/v1/payments/{$payment->id}/initiate")->assertOk();

        $checkoutUrl = $response->json('data.checkout_url');

        $this->assertStringContainsString('https://sandbox.nmb.co.tz/pay/mock', $checkoutUrl);
        $this->assertStringContainsString('PAY-2026-000001', $checkoutUrl);
    }

    public function test_ownership_validation(): void
    {
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
