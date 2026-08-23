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
        $orderStatus = $order->status instanceof OrderStatus
            ? $order->status
            : OrderStatus::tryFrom((string) $order->status);

        $transaction = $this->resolveAuthoritativeTransaction($order);

        if ($transaction !== null) {
            $transactionStatus = $this->transactionStatus($transaction);

            if ($transactionStatus === PaymentTransactionStatus::Successful) {
                return PaymentStatus::Paid->value;
            }

            if ($orderStatus === OrderStatus::Cancelled) {
                return PaymentStatus::Cancelled->value;
            }

            if ($orderStatus === OrderStatus::Refunded) {
                return PaymentStatus::Refunded->value;
            }

            return $this->mapTransactionStatus($transaction);
        }

        $payment = $this->resolveLatestPayment($order);
        $legacyStatus = $payment?->status instanceof PaymentStatus
            ? $payment->status->value
            : (filled($payment?->status) ? (string) $payment->status : null);

        if ($legacyStatus !== null) {
            if ($orderStatus === OrderStatus::Cancelled
                && ! in_array($legacyStatus, [PaymentStatus::Paid->value, PaymentStatus::Refunded->value], true)
            ) {
                return PaymentStatus::Cancelled->value;
            }

            return $legacyStatus;
        }

        if ($order->paid_at !== null) {
            return PaymentStatus::Paid->value;
        }

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
