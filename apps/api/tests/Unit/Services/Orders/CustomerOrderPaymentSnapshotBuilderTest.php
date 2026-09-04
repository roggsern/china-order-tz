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
use App\Services\Orders\CustomerOrderPaymentSnapshotBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class CustomerOrderPaymentSnapshotBuilderTest extends TestCase
{
    use RefreshDatabase;

    public function test_builds_from_successful_payment_transaction(): void
    {
        $user = User::factory()->create();
        $completedAt = now()->subMinutes(5);

        $order = Order::factory()->create([
            'user_id' => $user->id,
            'status' => OrderStatus::Processing,
            'paid_at' => $completedAt,
        ]);

        PaymentTransaction::factory()->create([
            'order_id' => $order->id,
            'provider' => PaymentProvider::Nmb,
            'merchant_reference' => 'COTZ-PAY-UNIT-001',
            'status' => PaymentTransactionStatus::Successful,
            'initiated_at' => now()->subMinutes(10),
            'completed_at' => $completedAt,
        ]);

        $order->load(['payments', 'paymentTransactions']);

        $snapshot = app(CustomerOrderPaymentSnapshotBuilder::class)->build($order);

        $this->assertSame(PaymentStatus::Paid->value, $snapshot['payment_status']);
        $this->assertSame(PaymentProvider::Nmb->value, $snapshot['payment_method']);
        $this->assertSame('COTZ-PAY-UNIT-001', $snapshot['reference']);
        $this->assertSame(PaymentProvider::Nmb->value, $snapshot['provider']);
        $this->assertNotNull($snapshot['paid_at']);
        $this->assertNotNull($snapshot['initiated_at']);
    }

    public function test_builds_from_latest_payment_when_no_successful_transaction(): void
    {
        $user = User::factory()->create();

        $order = Order::factory()->create([
            'user_id' => $user->id,
            'status' => OrderStatus::PendingPayment,
        ]);

        Payment::factory()->create([
            'order_id' => $order->id,
            'user_id' => $user->id,
            'method' => PaymentMethod::Mpesa,
            'status' => PaymentStatus::Pending,
            'reference' => 'PAY-UNIT-002',
        ]);

        $order->load(['payments', 'paymentTransactions']);

        $snapshot = app(CustomerOrderPaymentSnapshotBuilder::class)->build($order);

        $this->assertSame(PaymentStatus::Pending->value, $snapshot['payment_status']);
        $this->assertSame(PaymentMethod::Mpesa->value, $snapshot['payment_method']);
        $this->assertSame('PAY-UNIT-002', $snapshot['reference']);
        $this->assertNull($snapshot['provider']);
    }

    public function test_builds_from_active_payment_transaction_when_no_successful_transaction(): void
    {
        $user = User::factory()->create();

        $order = Order::factory()->create([
            'user_id' => $user->id,
            'status' => OrderStatus::PendingPayment,
        ]);

        PaymentTransaction::factory()->processing()->create([
            'order_id' => $order->id,
            'provider' => PaymentProvider::Nmb,
            'merchant_reference' => 'COTZ-PAY-ACTIVE-001',
            'amount' => 42000,
            'currency' => 'TZS',
        ]);

        $order->load(['payments', 'paymentTransactions']);

        $snapshot = app(CustomerOrderPaymentSnapshotBuilder::class)->build($order);

        $this->assertSame(PaymentStatus::Initiated->value, $snapshot['payment_status']);
        $this->assertSame(PaymentProvider::Nmb->value, $snapshot['payment_method']);
        $this->assertSame('COTZ-PAY-ACTIVE-001', $snapshot['reference']);
        $this->assertSame(PaymentProvider::Nmb->value, $snapshot['provider']);
    }

    public function test_builds_order_fallback_when_no_payment_records_exist(): void
    {
        $paidAt = now()->subHour();

        $order = Order::factory()->create([
            'status' => OrderStatus::Delivered,
            'paid_at' => $paidAt,
            'total' => 50000,
            'currency' => 'TZS',
        ]);

        $order->load(['payments', 'paymentTransactions']);

        $snapshot = app(CustomerOrderPaymentSnapshotBuilder::class)->build($order);

        $this->assertSame(PaymentStatus::Paid->value, $snapshot['payment_status']);
        $this->assertNull($snapshot['payment_method']);
        $this->assertNull($snapshot['reference']);
        $this->assertSame('50000.00', $snapshot['amount']);
        $this->assertSame('TZS', $snapshot['currency']);
        $this->assertNotNull($snapshot['paid_at']);
        $this->assertNull($snapshot['initiated_at']);
    }

    public function test_paid_at_falls_back_from_transaction_to_payment_to_order(): void
    {
        $user = User::factory()->create();
        $orderPaidAt = now()->subHours(3);
        $paymentPaidAt = now()->subHours(2);

        $order = Order::factory()->create([
            'user_id' => $user->id,
            'status' => OrderStatus::Paid,
            'paid_at' => $orderPaidAt,
        ]);

        Payment::factory()->create([
            'order_id' => $order->id,
            'user_id' => $user->id,
            'method' => PaymentMethod::Cash,
            'status' => PaymentStatus::Paid,
            'paid_at' => $paymentPaidAt,
        ]);

        PaymentTransaction::factory()->create([
            'order_id' => $order->id,
            'provider' => PaymentProvider::Snippe,
            'status' => PaymentTransactionStatus::Successful,
            'completed_at' => null,
            'merchant_reference' => 'COTZ-PAY-NO-COMPLETED',
        ]);

        $order->load(['payments', 'paymentTransactions']);
        $snapshot = app(CustomerOrderPaymentSnapshotBuilder::class)->build($order);

        $this->assertSame($paymentPaidAt->timestamp, Carbon::parse($snapshot['paid_at'])->timestamp);
    }
}
