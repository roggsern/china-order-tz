<?php

namespace App\Services\Payments;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Admin;
use App\Models\Order;
use App\Models\Payment;
use App\Services\Orders\Lifecycle\OrderLifecycleContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Confirms a Pay at Office (cash) payment, then converges into PaidOrderCompletionService.
 */
class ManualPaymentConfirmationService
{
    public function __construct(
        private readonly PaidOrderCompletionService $paidCompletion,
    ) {}

    public function confirm(
        Order $order,
        Admin $admin,
        ?string $reference = null,
        ?string $note = null,
    ): Order {
        return DB::transaction(function () use ($order, $admin, $reference, $note): Order {
            /** @var Order $lockedOrder */
            $lockedOrder = Order::query()
                ->whereKey($order->id)
                ->lockForUpdate()
                ->firstOrFail();

            $payment = $this->findCashPayment($lockedOrder);
            if ($payment === null) {
                $this->throwValidationError('payment', 'This order has no Pay at Office payment to confirm.');
            }

            /** @var Payment $lockedPayment */
            $lockedPayment = Payment::query()
                ->whereKey($payment->id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->assertPaymentBelongsToOrder($lockedPayment, $lockedOrder);
            $this->assertMethodIsCash($lockedPayment);
            $this->assertOrderEligible($lockedOrder);
            $this->assertPaymentEligible($lockedPayment);

            $alreadyConfirmed = $this->isFullyConfirmed($lockedOrder, $lockedPayment);

            if (! $alreadyConfirmed) {
                $this->assertAmountAndCurrency($lockedOrder, $lockedPayment);
                $this->markPaymentPaid($lockedPayment, $admin, $reference, $note);
            }

            $context = new OrderLifecycleContext(
                source: 'admin_pay',
                reason: $note !== null && trim($note) !== ''
                    ? $note
                    : 'Pay at Office payment confirmed',
                admin: $admin,
                idempotencyKey: 'office-pay:'.$lockedPayment->id,
                metadata: [
                    'path' => 'ManualPaymentConfirmationService',
                    'payment_id' => $lockedPayment->id,
                    'payment_method' => PaymentMethod::Cash->value,
                    'amount' => (string) $lockedPayment->amount,
                    'currency' => $lockedPayment->currency,
                    'reference' => $reference,
                    'source' => 'pay_at_office',
                ],
            );

            $paid = $this->paidCompletion->complete(
                $lockedOrder->fresh() ?? $lockedOrder,
                $context,
                inventorySource: 'admin_pay',
                inventoryActor: $admin,
                inventoryStrict: ! $alreadyConfirmed,
                inventoryMetadata: [
                    'path' => 'ManualPaymentConfirmationService',
                    'payment_id' => $lockedPayment->id,
                ],
            );

            $this->assertConsistentPaidState($paid, $lockedPayment->fresh() ?? $lockedPayment);

            return $paid->load([
                'user',
                'coupon',
                'items.product',
                'items.variant',
                'payments',
                'shippingAddress',
                'fulfillment',
                'statusHistory',
            ]);
        });
    }

    public function findCashPayment(Order $order): ?Payment
    {
        return Payment::query()
            ->where('order_id', $order->id)
            ->where('method', PaymentMethod::Cash)
            ->orderByDesc('created_at')
            ->first();
    }

    public function hasBlockingNonCashPayment(Order $order): bool
    {
        return Payment::query()
            ->where('order_id', $order->id)
            ->where('method', '!=', PaymentMethod::Cash->value)
            ->whereIn('status', [
                PaymentStatus::Pending->value,
                PaymentStatus::Initiated->value,
                PaymentStatus::Paid->value,
            ])
            ->exists();
    }

    private function assertPaymentBelongsToOrder(Payment $payment, Order $order): void
    {
        if ($payment->order_id !== $order->id) {
            $this->throwValidationError('payment', 'Payment does not belong to this order.');
        }
    }

    private function assertMethodIsCash(Payment $payment): void
    {
        if ($payment->method !== PaymentMethod::Cash) {
            $this->throwValidationError('payment_method', 'Only Pay at Office (cash) payments can be confirmed this way.');
        }
    }

    private function assertOrderEligible(Order $order): void
    {
        $status = $order->status instanceof OrderStatus
            ? $order->status
            : OrderStatus::tryFrom((string) $order->status);

        if ($status === OrderStatus::Cancelled) {
            $this->throwValidationError('order', 'Cancelled orders cannot be confirmed as paid.');
        }

        if (in_array($status, [OrderStatus::Refunded, OrderStatus::RefundPending], true)) {
            $this->throwValidationError('order', 'Refunded orders cannot be confirmed as paid.');
        }

        if ($status === OrderStatus::Paid && $order->paid_at !== null) {
            return;
        }

        if ($status === null || ! $status->isPayable()) {
            $this->throwValidationError('order', 'Only unpaid Pay at Office orders can be confirmed.');
        }
    }

    private function assertPaymentEligible(Payment $payment): void
    {
        $status = $payment->status instanceof PaymentStatus
            ? $payment->status
            : PaymentStatus::tryFrom((string) $payment->status);

        if ($status === PaymentStatus::Paid && $payment->paid_at !== null) {
            return;
        }

        if (in_array($status, [PaymentStatus::Failed, PaymentStatus::Expired], true)) {
            $this->throwValidationError('payment', 'This Pay at Office payment can no longer be confirmed.');
        }

        if (in_array($status, [PaymentStatus::Refunded, PaymentStatus::Cancelled], true)) {
            $this->throwValidationError('payment', 'This Pay at Office payment can no longer be confirmed.');
        }

        if (! in_array($status, [PaymentStatus::Pending, PaymentStatus::Initiated], true)) {
            $this->throwValidationError('payment', 'This Pay at Office payment is not awaiting confirmation.');
        }
    }

    private function assertAmountAndCurrency(Order $order, Payment $payment): void
    {
        $expected = (string) ($order->grand_total ?? $order->total);
        $actual = (string) $payment->amount;

        if (bccomp($actual, $expected, 2) !== 0) {
            $this->throwValidationError(
                'amount',
                'Confirmed amount must equal the order total. Partial payments are not supported.',
            );
        }

        if (bccomp($actual, '0', 2) <= 0) {
            $this->throwValidationError('amount', 'Payment amount must be greater than zero.');
        }

        $orderCurrency = strtoupper((string) ($order->currency ?: 'TZS'));
        $paymentCurrency = strtoupper((string) ($payment->currency ?: 'TZS'));

        if ($orderCurrency !== $paymentCurrency) {
            $this->throwValidationError('currency', 'Payment currency does not match the order currency.');
        }
    }

    private function isFullyConfirmed(Order $order, Payment $payment): bool
    {
        $orderPaid = $order->status === OrderStatus::Paid && $order->paid_at !== null;
        $paymentPaid = $payment->status === PaymentStatus::Paid && $payment->paid_at !== null;

        return $orderPaid && $paymentPaid;
    }

    private function markPaymentPaid(
        Payment $payment,
        Admin $admin,
        ?string $reference,
        ?string $note,
    ): void {
        $metadata = is_array($payment->metadata) ? $payment->metadata : [];
        $metadata['office_confirmation'] = [
            'confirmed_by_admin_id' => $admin->id,
            'confirmed_at' => now()->toIso8601String(),
            'reference' => $reference,
            'note' => $note,
            'source' => 'pay_at_office',
        ];

        $payment->fill([
            'status' => PaymentStatus::Paid,
            'paid_at' => $payment->paid_at ?? now(),
            'metadata' => $metadata,
        ]);

        if ($reference !== null && trim($reference) !== '' && $payment->reference === null) {
            $payment->reference = trim($reference);
        }

        $payment->save();
    }

    private function assertConsistentPaidState(Order $order, Payment $payment): void
    {
        $orderPaid = $order->status === OrderStatus::Paid && $order->paid_at !== null;
        $paymentPaid = $payment->status === PaymentStatus::Paid && $payment->paid_at !== null;

        if ($orderPaid !== $paymentPaid) {
            $this->throwValidationError(
                'payment',
                'Payment and order could not be confirmed together. No partial update was kept.',
            );
        }
    }

    private function throwValidationError(string $field, string $message): never
    {
        $exception = ValidationException::withMessages([
            $field => [$message],
        ]);
        $exception->response = response()->json([
            'success' => false,
            'message' => $message,
        ], 422);

        throw $exception;
    }
}
