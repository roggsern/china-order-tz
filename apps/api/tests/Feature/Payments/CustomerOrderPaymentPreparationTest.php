<?php

namespace Tests\Feature\Payments;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Admin;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Legacy Payment rows remain for non-gateway methods (COD/bank).
 * NMB must use Payment Orchestrator (POST /payments/start/{order}).
 */
class CustomerOrderPaymentPreparationTest extends TestCase
{
    use RefreshDatabase;

    private function createPendingOrder(User $user, array $overrides = []): Order
    {
        return $this->createPayableOrder($user, array_merge([
            'total' => 75000,
            'currency' => 'TZS',
        ], $overrides));
    }

    public function test_nmb_prepare_is_rejected_in_favor_of_orchestrator(): void
    {
        $user = User::factory()->create();
        $order = $this->createPendingOrder($user);

        Sanctum::actingAs($user);

        $this->postJson("/api/v1/orders/{$order->id}/payments", [
            'payment_method' => PaymentMethod::Nmb->value,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['payment_method']);
    }

    public function test_bank_transfer_payment_preparation(): void
    {
        $user = User::factory()->create();
        $order = $this->createPendingOrder($user);

        Sanctum::actingAs($user);

        $this->postJson("/api/v1/orders/{$order->id}/payments", [
            'payment_method' => PaymentMethod::BankTransfer->value,
        ])->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.order_id', $order->id)
            ->assertJsonPath('data.amount', '75000.00')
            ->assertJsonPath('data.currency', 'TZS')
            ->assertJsonPath('data.payment_method', 'bank_transfer')
            ->assertJsonPath('data.status', PaymentStatus::Initiated->value)
            ->assertJsonPath('data.ready_for_payment', true);

        $this->assertDatabaseHas('payments', [
            'order_id' => $order->id,
            'user_id' => $user->id,
            'method' => PaymentMethod::BankTransfer->value,
            'status' => PaymentStatus::Initiated->value,
            'amount' => '75000.00',
        ]);
    }

    public function test_existing_pending_payment_reused(): void
    {
        $user = User::factory()->create();
        $order = $this->createPendingOrder($user);

        $existingPayment = Payment::factory()->create([
            'order_id' => $order->id,
            'user_id' => $user->id,
            'method' => PaymentMethod::Cash,
            'status' => PaymentStatus::Pending,
            'amount' => 75000,
            'currency' => 'TZS',
            'reference' => 'PAY-2026-000099',
        ]);

        Sanctum::actingAs($user);

        $this->postJson("/api/v1/orders/{$order->id}/payments", [
            'payment_method' => PaymentMethod::Cash->value,
        ])->assertCreated()
            ->assertJsonPath('data.id', $existingPayment->id)
            ->assertJsonPath('data.reference', 'PAY-2026-000099')
            ->assertJsonPath('data.status', PaymentStatus::Initiated->value);

        $this->assertSame(1, Payment::query()->where('order_id', $order->id)->count());
    }

    public function test_customer_can_view_prepared_payment(): void
    {
        $user = User::factory()->create();
        $order = $this->createPendingOrder($user);

        Payment::factory()->create([
            'order_id' => $order->id,
            'user_id' => $user->id,
            'method' => PaymentMethod::BankTransfer,
            'status' => PaymentStatus::Initiated,
            'amount' => 75000,
            'reference' => 'PAY-2026-000010',
        ]);

        Sanctum::actingAs($user);

        $this->getJson("/api/v1/orders/{$order->id}/payment")
            ->assertOk()
            ->assertJsonPath('data.reference', 'PAY-2026-000010')
            ->assertJsonPath('data.payment_method', 'bank_transfer');
    }

    public function test_paid_order_rejected(): void
    {
        $user = User::factory()->create();
        $order = $this->createPendingOrder($user, [
            'status' => OrderStatus::Paid,
            'paid_at' => now(),
        ]);

        Sanctum::actingAs($user);

        $this->postJson("/api/v1/orders/{$order->id}/payments", [
            'payment_method' => PaymentMethod::Cash->value,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['order']);
    }

    public function test_order_with_paid_payment_rejected(): void
    {
        $user = User::factory()->create();
        $order = $this->createPendingOrder($user);

        Payment::factory()->create([
            'order_id' => $order->id,
            'user_id' => $user->id,
            'method' => PaymentMethod::Cash,
            'status' => PaymentStatus::Paid,
            'amount' => 75000,
            'paid_at' => now(),
        ]);

        Sanctum::actingAs($user);

        $this->postJson("/api/v1/orders/{$order->id}/payments", [
            'payment_method' => PaymentMethod::Cash->value,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['order']);
    }

    public function test_another_customers_order_rejected(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $order = $this->createPendingOrder($owner);

        Sanctum::actingAs($otherUser);

        $this->postJson("/api/v1/orders/{$order->id}/payments", [
            'payment_method' => PaymentMethod::Cash->value,
        ])->assertNotFound();

        $this->getJson("/api/v1/orders/{$order->id}/payment")->assertNotFound();
    }

    public function test_guest_rejected(): void
    {
        $order = Order::factory()->pending()->create();

        $this->postJson("/api/v1/orders/{$order->id}/payments", [
            'payment_method' => PaymentMethod::Cash->value,
        ])->assertUnauthorized();

        $this->getJson("/api/v1/orders/{$order->id}/payment")->assertUnauthorized();
    }

    public function test_admin_rejected(): void
    {
        Sanctum::actingAs(Admin::factory()->create());
        $order = Order::factory()->pending()->create();

        $this->postJson("/api/v1/orders/{$order->id}/payments", [
            'payment_method' => PaymentMethod::Cash->value,
        ])->assertUnauthorized();

        $this->getJson("/api/v1/orders/{$order->id}/payment")->assertUnauthorized();
    }

    public function test_payment_reference_generated(): void
    {
        $user = User::factory()->create();
        $order = $this->createPendingOrder($user);

        Sanctum::actingAs($user);

        $response = $this->postJson("/api/v1/orders/{$order->id}/payments", [
            'payment_method' => PaymentMethod::BankTransfer->value,
        ])->assertCreated();

        $reference = $response->json('data.reference');

        $this->assertMatchesRegularExpression('/^PAY-\d{4}-\d{6}$/', $reference);
        $this->assertDatabaseHas('payments', [
            'order_id' => $order->id,
            'reference' => $reference,
        ]);
    }
}
