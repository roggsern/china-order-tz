<?php

namespace Tests\Unit\Services\Orders;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentProvider;
use App\Enums\PaymentStatus;
use App\Enums\PaymentTransactionStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentTransaction;
use App\Models\User;
use App\Services\Orders\CustomerOrderPaymentStatusResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerOrderPaymentStatusResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_processing_transaction_maps_to_initiated(): void
    {
        $order = Order::factory()->create([
            'status' => OrderStatus::PendingPayment,
        ]);

        PaymentTransaction::factory()->processing()->create([
            'order_id' => $order->id,
            'provider' => PaymentProvider::Nmb,
        ]);

        $order->load('paymentTransactions');

        $this->assertSame(
            PaymentStatus::Initiated->value,
            app(CustomerOrderPaymentStatusResolver::class)->resolve($order),
        );
    }

    public function test_successful_transaction_takes_priority_over_legacy_payment(): void
    {
        $user = User::factory()->create();

        $order = Order::factory()->create([
            'user_id' => $user->id,
            'status' => OrderStatus::Processing,
            'paid_at' => now(),
        ]);

        Payment::factory()->create([
            'order_id' => $order->id,
            'user_id' => $user->id,
            'method' => PaymentMethod::Mpesa,
            'status' => PaymentStatus::Initiated,
        ]);

        PaymentTransaction::factory()->create([
            'order_id' => $order->id,
            'provider' => PaymentProvider::Nmb,
            'status' => PaymentTransactionStatus::Successful,
            'completed_at' => now(),
        ]);

        $order->load(['payments', 'paymentTransactions']);

        $this->assertSame(
            PaymentStatus::Paid->value,
            app(CustomerOrderPaymentStatusResolver::class)->resolve($order),
        );
    }

    public function test_active_transaction_takes_priority_over_legacy_payment(): void
    {
        $user = User::factory()->create();

        $order = Order::factory()->create([
            'user_id' => $user->id,
            'status' => OrderStatus::PendingPayment,
        ]);

        Payment::factory()->create([
            'order_id' => $order->id,
            'user_id' => $user->id,
            'status' => PaymentStatus::Pending,
        ]);

        PaymentTransaction::factory()->processing()->create([
            'order_id' => $order->id,
            'provider' => PaymentProvider::Nmb,
        ]);

        $order->load(['payments', 'paymentTransactions']);

        $this->assertSame(
            PaymentStatus::Initiated->value,
            app(CustomerOrderPaymentStatusResolver::class)->resolve($order),
        );
    }
}
