<?php

namespace Tests\Feature\Payments;

use App\Models\Admin;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Legacy POST /payments/{payment}/initiate is retired.
 * Production payments use POST /payments/start/{order}.
 */
class CustomerPaymentSessionTest extends TestCase
{
    use RefreshDatabase;

    public function test_legacy_initiate_returns_gone(): void
    {
        $user = User::factory()->create();
        $payment = Payment::factory()->nmb()->create(['user_id' => $user->id]);

        Sanctum::actingAs($user);

        $this->postJson("/api/v1/payments/{$payment->id}/initiate")
            ->assertStatus(410)
            ->assertJsonPath('success', false)
            ->assertJsonPath('deprecated', true)
            ->assertJsonPath('replacement', '/api/v1/payments/start/{order}');
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
