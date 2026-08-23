<?php

namespace Tests\Feature\CustomerOrders;

use App\Enums\OrderStatus;
use App\Enums\PaymentProvider;
use App\Enums\PaymentStatus;
use App\Enums\PaymentTransactionStatus;
use App\Models\Order;
use App\Models\PaymentTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CancelledOrderCustomerDisplayTest extends TestCase
{
    use RefreshDatabase;

    public function test_cancelled_order_resource_returns_cancelled_not_awaiting_payment(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'status' => OrderStatus::Cancelled,
            'paid_at' => null,
            'total' => 45000,
        ]);

        Sanctum::actingAs($user);

        $this->getJson("/api/v1/orders/{$order->id}")
            ->assertOk()
            ->assertJsonPath('data.status', OrderStatus::Cancelled->value)
            ->assertJsonPath('data.can_pay', false)
            ->assertJsonPath('data.progress.current_key', 'CANCELLED')
            ->assertJsonPath('data.progress.current_label', 'Order cancelled')
            ->assertJsonPath('data.payment.payment_status', PaymentStatus::Cancelled->value)
            ->assertJsonPath('data.active_payment_transaction', null);
    }

    public function test_stale_nmb_processing_transaction_does_not_override_cancelled_order(): void
    {
        $user = User::factory()->create();
        $order = $this->createCancelledOrderWithProcessingTransaction($user, PaymentProvider::Nmb);

        Sanctum::actingAs($user);

        $this->getJson("/api/v1/orders/{$order->id}")
            ->assertOk()
            ->assertJsonPath('data.status', OrderStatus::Cancelled->value)
            ->assertJsonPath('data.can_pay', false)
            ->assertJsonPath('data.progress.current_key', 'CANCELLED')
            ->assertJsonPath('data.progress.current_label', 'Order cancelled')
            ->assertJsonPath('data.payment.payment_status', PaymentStatus::Cancelled->value)
            ->assertJsonPath('data.payment.payment_method', PaymentProvider::Nmb->value)
            ->assertJsonPath('data.active_payment_transaction', null);

        $this->getJson('/api/v1/orders')
            ->assertOk()
            ->assertJsonPath('data.0.status', OrderStatus::Cancelled->value)
            ->assertJsonPath('data.0.can_pay', false)
            ->assertJsonPath('data.0.payment_status', PaymentStatus::Cancelled->value)
            ->assertJsonPath('data.0.progress.current_key', 'CANCELLED')
            ->assertJsonPath('data.0.active_payment_transaction', null);

        $this->postJson("/api/v1/payments/start/{$order->id}")
            ->assertStatus(422)
            ->assertJsonValidationErrors(['order']);
    }

    public function test_stale_snippe_processing_transaction_does_not_override_cancelled_order(): void
    {
        $user = User::factory()->create();
        $order = $this->createCancelledOrderWithProcessingTransaction($user, PaymentProvider::Snippe);

        Sanctum::actingAs($user);

        $this->getJson("/api/v1/orders/{$order->id}")
            ->assertOk()
            ->assertJsonPath('data.status', OrderStatus::Cancelled->value)
            ->assertJsonPath('data.can_pay', false)
            ->assertJsonPath('data.progress.current_key', 'CANCELLED')
            ->assertJsonPath('data.payment.payment_status', PaymentStatus::Cancelled->value)
            ->assertJsonPath('data.payment.payment_method', PaymentProvider::Snippe->value)
            ->assertJsonPath('data.active_payment_transaction', null);
    }

    public function test_pending_payment_order_still_projects_awaiting_payment(): void
    {
        $user = User::factory()->create();
        $order = $this->createPayableOrder($user, ['total' => 45000]);

        PaymentTransaction::factory()->processing()->create([
            'order_id' => $order->id,
            'provider' => PaymentProvider::Nmb,
            'amount' => 45000,
        ]);

        Sanctum::actingAs($user);

        $this->getJson("/api/v1/orders/{$order->id}")
            ->assertOk()
            ->assertJsonPath('data.status', OrderStatus::PendingPayment->value)
            ->assertJsonPath('data.can_pay', true)
            ->assertJsonPath('data.progress.current_key', 'AWAITING_PAYMENT')
            ->assertJsonPath('data.payment.payment_status', PaymentStatus::Initiated->value);
    }

    public function test_paid_order_behavior_is_unchanged(): void
    {
        $user = User::factory()->create();
        $paidAt = now()->subHour();
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'status' => OrderStatus::Processing,
            'paid_at' => $paidAt,
            'total' => 45000,
        ]);

        PaymentTransaction::factory()->create([
            'order_id' => $order->id,
            'provider' => PaymentProvider::Nmb,
            'status' => PaymentTransactionStatus::Successful,
            'completed_at' => $paidAt,
            'amount' => 45000,
        ]);

        Sanctum::actingAs($user);

        $this->getJson("/api/v1/orders/{$order->id}")
            ->assertOk()
            ->assertJsonPath('data.status', OrderStatus::Processing->value)
            ->assertJsonPath('data.can_pay', false)
            ->assertJsonPath('data.payment.payment_status', PaymentStatus::Paid->value);
    }

    private function createCancelledOrderWithProcessingTransaction(
        User $user,
        PaymentProvider $provider,
    ): Order {
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'status' => OrderStatus::Cancelled,
            'paid_at' => null,
            'total' => 45000,
            'created_at' => now()->subDays(10),
        ]);

        PaymentTransaction::factory()->processing()->create([
            'order_id' => $order->id,
            'provider' => $provider,
            'amount' => 45000,
            'created_at' => now()->subDays(10),
        ]);

        return $order->fresh(['payments', 'paymentTransactions']) ?? $order;
    }
}
