<?php

namespace App\Services\Orders;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\PaymentTransactionStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentTransaction;
use Illuminate\Support\Collection;

/**
 * Resolves customer-facing payment status for order list/detail surfaces.
 * Payment transactions are authoritative over legacy payment rows.
 */
class CustomerOrderPaymentStatusResolver
{
    public function resolve(Order $order): string
    {
        $transaction = $this->resolveAuthoritativeTransaction($order);

        if ($transaction !== null) {
            return $this->mapTransactionStatus($transaction);
        }

        $payment = $this->resolveLatestPayment($order);

        if ($payment?->status instanceof PaymentStatus) {
            return $payment->status->value;
        }

        if ($payment !== null && filled($payment->status)) {
            return (string) $payment->status;
        }

        if ($order->paid_at !== null) {
            return PaymentStatus::Paid->value;
        }

        $orderStatus = $order->status instanceof OrderStatus
            ? $order->status
            : OrderStatus::tryFrom((string) $order->status);

        return match ($orderStatus) {
            OrderStatus::Cancelled => PaymentStatus::Cancelled->value,
            OrderStatus::Refunded => PaymentStatus::Refunded->value,
            OrderStatus::Pending, OrderStatus::PendingPayment => PaymentStatus::Pending->value,
            OrderStatus::Paid,
            OrderStatus::Confirmed,
            OrderStatus::Processing,
            OrderStatus::Shipped,
            OrderStatus::Delivered,
            OrderStatus::Completed,
            OrderStatus::RefundPending => PaymentStatus::Paid->value,
            default => PaymentStatus::Pending->value,
        };
    }

    private function resolveAuthoritativeTransaction(Order $order): ?PaymentTransaction
    {
        $transactions = $this->resolveTransactions($order);

        if ($transactions->isEmpty()) {
            return null;
        }

        $successful = $transactions
            ->filter(fn (PaymentTransaction $transaction): bool => $this->transactionStatus($transaction) === PaymentTransactionStatus::Successful)
            ->sortByDesc(fn (PaymentTransaction $transaction) => $transaction->completed_at ?? $transaction->created_at)
            ->first();

        if ($successful !== null) {
            return $successful;
        }

        $active = $transactions
            ->filter(fn (PaymentTransaction $transaction): bool => in_array(
                $this->transactionStatus($transaction),
                [PaymentTransactionStatus::Pending, PaymentTransactionStatus::Processing],
                true,
            ))
            ->sortByDesc('created_at')
            ->first();

        if ($active !== null) {
            return $active;
        }

        return $transactions->sortByDesc('created_at')->first();
    }

    private function mapTransactionStatus(PaymentTransaction $transaction): string
    {
        return match ($this->transactionStatus($transaction)) {
            PaymentTransactionStatus::Successful => PaymentStatus::Paid->value,
            PaymentTransactionStatus::Processing => PaymentStatus::Initiated->value,
            PaymentTransactionStatus::Pending => PaymentStatus::Pending->value,
            PaymentTransactionStatus::Failed => PaymentStatus::Failed->value,
            PaymentTransactionStatus::Cancelled => PaymentStatus::Cancelled->value,
        };
    }

    /**
     * @return Collection<int, PaymentTransaction>
     */
    private function resolveTransactions(Order $order): Collection
    {
        if ($order->relationLoaded('paymentTransactions')) {
            return $order->paymentTransactions;
        }

        return $order->paymentTransactions()->get();
    }

    private function transactionStatus(PaymentTransaction $transaction): PaymentTransactionStatus
    {
        return $transaction->status instanceof PaymentTransactionStatus
            ? $transaction->status
            : PaymentTransactionStatus::from((string) $transaction->status);
    }

    private function resolveLatestPayment(Order $order): ?Payment
    {
        if ($order->relationLoaded('payments')) {
            return $order->payments->sortByDesc('created_at')->first();
        }

        return $order->payments()->latest()->first();
    }
}
