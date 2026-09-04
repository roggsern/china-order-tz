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
use App\Services\Orders\AdminOrderPaymentSnapshotPresenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AdminOrderPaymentSnapshotPresenterTest extends TestCase
{
    use RefreshDatabase;

    public function test_snippe_successful_transaction_sets_mobile_money_method_and_snippe_provider(): void
    {
        $order = $this->paidOrder();
        $completedAt = now()->subMinutes(8);

        PaymentTransaction::factory()->create([
            'order_id' => $order->id,
            'provider' => PaymentProvider::Snippe,
            'merchant_reference' => 'COTZ-PAY-SNIPPE-001',
            'status' => PaymentTransactionStatus::Successful,
            'completed_at' => $completedAt,
        ]);

        $snapshot = $this->present($order);

        $this->assertSame(PaymentStatus::Paid->value, $snapshot['payment_status']);
        $this->assertSame(PaymentMethod::Snippe->value, $snapshot['payment_method']);
        $this->assertSame(PaymentProvider::Snippe->value, $snapshot['provider']);
        $this->assertSame('COTZ-PAY-SNIPPE-001', $snapshot['reference']);
        $this->assertNotNull($snapshot['paid_at']);
        $this->assertSame(
            ['payment_status', 'payment_method', 'provider', 'reference', 'paid_at'],
            array_keys($snapshot),
        );
    }

    public function test_nmb_successful_transaction_sets_nmb_method_and_provider(): void
    {
        $order = $this->paidOrder();

        PaymentTransaction::factory()->create([
            'order_id' => $order->id,
            'provider' => PaymentProvider::Nmb,
            'merchant_reference' => 'COTZ-PAY-NMB-001',
            'status' => PaymentTransactionStatus::Successful,
            'completed_at' => now()->subMinutes(3),
        ]);

        $snapshot = $this->present($order);

        $this->assertSame(PaymentMethod::Nmb->value, $snapshot['payment_method']);
        $this->assertSame(PaymentProvider::Nmb->value, $snapshot['provider']);
        $this->assertSame('COTZ-PAY-NMB-001', $snapshot['reference']);
    }

    public function test_pay_at_office_uses_cash_method_and_office_provider_without_transaction(): void
    {
        $user = User::factory()->create();
        $paidAt = now()->subMinutes(12);
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'status' => OrderStatus::Paid,
            'paid_at' => $paidAt,
        ]);

        Payment::factory()->create([
            'order_id' => $order->id,
            'user_id' => $user->id,
            'method' => PaymentMethod::Cash,
            'status' => PaymentStatus::Paid,
            'reference' => 'OFFICE-001',
            'paid_at' => $paidAt,
        ]);

        $this->assertSame(0, PaymentTransaction::query()->where('order_id', $order->id)->count());

        $snapshot = $this->present($order);

        $this->assertSame(PaymentMethod::Cash->value, $snapshot['payment_method']);
        $this->assertSame(AdminOrderPaymentSnapshotPresenter::OFFICE_PROVIDER, $snapshot['provider']);
        $this->assertSame('OFFICE-001', $snapshot['reference']);
    }

    public function test_selects_successful_transaction_over_newer_failed_or_pending_attempt(): void
    {
        $order = $this->paidOrder();

        PaymentTransaction::factory()->create([
            'order_id' => $order->id,
            'provider' => PaymentProvider::Snippe,
            'merchant_reference' => 'COTZ-PAY-SUCCESS',
            'status' => PaymentTransactionStatus::Successful,
            'created_at' => now()->subMinutes(20),
            'completed_at' => now()->subMinutes(18),
        ]);
        PaymentTransaction::factory()->create([
            'order_id' => $order->id,
            'provider' => PaymentProvider::Snippe,
            'merchant_reference' => 'COTZ-PAY-FAILED-LATER',
            'status' => PaymentTransactionStatus::Failed,
            'created_at' => now()->subMinutes(5),
            'completed_at' => null,
        ]);
        PaymentTransaction::factory()->create([
            'order_id' => $order->id,
            'provider' => PaymentProvider::Snippe,
            'merchant_reference' => 'COTZ-PAY-PENDING-LATER',
            'status' => PaymentTransactionStatus::Pending,
            'created_at' => now()->subMinutes(1),
        ]);

        $snapshot = $this->present($order);

        $this->assertSame('COTZ-PAY-SUCCESS', $snapshot['reference']);
        $this->assertSame(PaymentProvider::Snippe->value, $snapshot['provider']);
    }

    public function test_selects_latest_successful_transaction_by_completed_at(): void
    {
        $order = $this->paidOrder();

        PaymentTransaction::factory()->create([
            'order_id' => $order->id,
            'provider' => PaymentProvider::Nmb,
            'merchant_reference' => 'COTZ-PAY-OLDER-SUCCESS',
            'status' => PaymentTransactionStatus::Successful,
            'completed_at' => now()->subMinutes(30),
            'created_at' => now()->subMinutes(40),
        ]);
        PaymentTransaction::factory()->create([
            'order_id' => $order->id,
            'provider' => PaymentProvider::Snippe,
            'merchant_reference' => 'COTZ-PAY-NEWER-SUCCESS',
            'status' => PaymentTransactionStatus::Successful,
            'completed_at' => now()->subMinutes(2),
            'created_at' => now()->subMinutes(10),
        ]);

        $snapshot = $this->present($order);

        $this->assertSame('COTZ-PAY-NEWER-SUCCESS', $snapshot['reference']);
        $this->assertSame(PaymentProvider::Snippe->value, $snapshot['provider']);
    }

    public function test_paid_at_prefers_transaction_completed_at_then_payment_then_order(): void
    {
        $user = User::factory()->create();
        $orderPaidAt = now()->subHours(3);
        $paymentPaidAt = now()->subHours(2);
        $txnCompletedAt = now()->subHour();

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
            'merchant_reference' => 'COTZ-PAY-PRECEDENCE',
            'status' => PaymentTransactionStatus::Successful,
            'completed_at' => $txnCompletedAt,
        ]);

        $snapshot = $this->present($order);
        $this->assertSame($txnCompletedAt->timestamp, Carbon::parse($snapshot['paid_at'])->timestamp);

        PaymentTransaction::query()->where('order_id', $order->id)->delete();
        $order->unsetRelation('paymentTransactions');
        $order->unsetRelation('payments');

        $fromPayment = $this->present($order->fresh());
        $this->assertSame($paymentPaidAt->timestamp, Carbon::parse($fromPayment['paid_at'])->timestamp);

        Payment::query()->where('order_id', $order->id)->delete();
        $fromOrder = $this->present($order->fresh());
        $this->assertSame($orderPaidAt->timestamp, Carbon::parse($fromOrder['paid_at'])->timestamp);
    }

    public function test_falls_back_to_provider_reference_when_merchant_reference_missing(): void
    {
        $order = $this->paidOrder();

        PaymentTransaction::factory()->create([
            'order_id' => $order->id,
            'provider' => PaymentProvider::Snippe,
            'merchant_reference' => '',
            'provider_reference' => 'SNIPPE-REF-1306',
            'status' => PaymentTransactionStatus::Successful,
            'completed_at' => now()->subMinute(),
        ]);

        $snapshot = $this->present($order);

        $this->assertSame('SNIPPE-REF-1306', $snapshot['reference']);
    }

    /**
     * @return array{payment_status: string, payment_method: string|null, provider: string|null, reference: string|null, paid_at: string|null}
     */
    private function present(Order $order): array
    {
        $order->load(['payments', 'paymentTransactions']);

        return app(AdminOrderPaymentSnapshotPresenter::class)->present($order);
    }

    private function paidOrder(): Order
    {
        return Order::factory()->create([
            'status' => OrderStatus::Paid,
            'paid_at' => now()->subMinutes(4),
        ]);
    }
}
