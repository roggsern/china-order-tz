<?php

namespace App\Services\Orders;

use App\Enums\PaymentProvider;
use App\Enums\PaymentTransactionStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentTransaction;
use Illuminate\Support\Carbon;

/**
 * Builds a unified customer-facing payment snapshot for order detail surfaces.
 */
class CustomerOrderPaymentSnapshotBuilder
{
    public function __construct(
        private readonly CustomerOrderPaymentStatusResolver $paymentStatusResolver,
    ) {}

    /**
     * @return array{
     *     payment_status: string,
     *     payment_method: string|null,
     *     reference: string|null,
     *     provider: string|null,
     *     amount: string,
     *     currency: string,
     *     paid_at: Carbon|null,
     *     initiated_at: Carbon|null,
     * }
     */
    public function build(Order $order): array
    {
        $fields = $this->resolveFields($order);

        return [
            'payment_status' => $this->paymentStatusResolver->resolve($order),
            ...$fields,
        ];
    }

    /**
     * @return array{
     *     payment_method: string|null,
     *     reference: string|null,
     *     provider: string|null,
     *     amount: string,
     *     currency: string,
     *     paid_at: Carbon|null,
     *     initiated_at: Carbon|null,
     * }
     */
    private function resolveFields(Order $order): array
    {
        $transaction = $this->resolveLatestSuccessfulTransaction($order)
            ?? $this->resolveLatestActiveTransaction($order);

        if ($transaction !== null) {
            return $this->fromPaymentTransaction($transaction);
        }

        $payment = $this->resolveLatestPayment($order);
        if ($payment !== null) {
            return $this->fromPayment($payment, $order);
        }

        return $this->fromOrderFallback($order);
    }

    private function resolveLatestActiveTransaction(Order $order): ?PaymentTransaction
    {
        $transactions = $order->relationLoaded('paymentTransactions')
            ? $order->paymentTransactions
            : collect();

        return $transactions
            ->filter(function (PaymentTransaction $transaction): bool {
                $status = $transaction->status instanceof PaymentTransactionStatus
                    ? $transaction->status
                    : PaymentTransactionStatus::tryFrom((string) ($transaction->status ?? ''));

                return in_array($status, [
                    PaymentTransactionStatus::Pending,
                    PaymentTransactionStatus::Processing,
                ], true);
            })
            ->sortByDesc('created_at')
            ->first();
    }

    private function resolveLatestSuccessfulTransaction(Order $order): ?PaymentTransaction
    {
        $transactions = $order->relationLoaded('paymentTransactions')
            ? $order->paymentTransactions
            : collect();

        return $transactions
            ->filter(function (PaymentTransaction $transaction): bool {
                $status = $transaction->status instanceof PaymentTransactionStatus
                    ? $transaction->status
                    : PaymentTransactionStatus::tryFrom((string) ($transaction->status ?? ''));

                return $status === PaymentTransactionStatus::Successful;
            })
            ->sortByDesc(fn (PaymentTransaction $transaction) => $transaction->completed_at ?? $transaction->created_at)
            ->first();
    }

    private function resolveLatestPayment(Order $order): ?Payment
    {
        if ($order->relationLoaded('payments')) {
            return $order->payments->sortByDesc('created_at')->first();
        }

        return $order->payments()->latest()->first();
    }

    /**
     * @return array{
     *     payment_method: string|null,
     *     reference: string|null,
     *     provider: string|null,
     *     amount: string,
     *     currency: string,
     *     paid_at: Carbon|null,
     *     initiated_at: Carbon|null,
     * }
     */
    private function fromPaymentTransaction(PaymentTransaction $transaction): array
    {
        $provider = $transaction->provider instanceof PaymentProvider
            ? $transaction->provider->value
            : (filled($transaction->provider) ? (string) $transaction->provider : null);

        return [
            'payment_method' => $provider,
            'reference' => $transaction->merchant_reference,
            'provider' => $provider,
            'amount' => $this->formatAmount($transaction->amount),
            'currency' => strtoupper((string) ($transaction->currency ?: 'TZS')),
            'paid_at' => $transaction->completed_at,
            'initiated_at' => $transaction->initiated_at,
        ];
    }

    /**
     * @return array{
     *     payment_method: string|null,
     *     reference: string|null,
     *     provider: string|null,
     *     amount: string,
     *     currency: string,
     *     paid_at: Carbon|null,
     *     initiated_at: Carbon|null,
     * }
     */
    private function fromPayment(Payment $payment, Order $order): array
    {
        $method = $payment->method instanceof \App\Enums\PaymentMethod
            ? $payment->method->value
            : (filled($payment->method) ? (string) $payment->method : null);

        return [
            'payment_method' => $method,
            'reference' => $payment->reference,
            'provider' => null,
            'amount' => $this->formatAmount($payment->amount ?? $order->grand_total ?? $order->total),
            'currency' => strtoupper((string) ($payment->currency ?: $order->currency ?: 'TZS')),
            'paid_at' => $payment->paid_at,
            'initiated_at' => $payment->initiated_at,
        ];
    }

    /**
     * @return array{
     *     payment_method: string|null,
     *     reference: string|null,
     *     provider: string|null,
     *     amount: string,
     *     currency: string,
     *     paid_at: Carbon|null,
     *     initiated_at: Carbon|null,
     * }
     */
    private function fromOrderFallback(Order $order): array
    {
        return [
            'payment_method' => null,
            'reference' => null,
            'provider' => null,
            'amount' => $this->formatAmount($order->grand_total ?? $order->total),
            'currency' => strtoupper((string) ($order->currency ?: 'TZS')),
            'paid_at' => $order->paid_at,
            'initiated_at' => null,
        ];
    }

    private function formatAmount(mixed $amount): string
    {
        return number_format((float) $amount, 2, '.', '');
    }
}
