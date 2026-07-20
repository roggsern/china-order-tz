<?php

namespace App\Services\Payments;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Payments\Gateways\Nmb\NmbPaymentCompletionResult;
use App\Services\CostProfit\ProfitEngine;
use App\Services\Fulfillment\FulfillmentEngine;
use App\Services\Orders\Lifecycle\OrderLifecycleContext;
use App\Services\Orders\Lifecycle\OrderLifecycleEngine;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Legacy Payment-model NMB completion.
 * Kept for residual Payment rows; always starts Fulfillment (+ warehouse) like the orchestrator path.
 * New customer payments must use PaymentOrchestrator / PaymentTransactionCompletionService.
 *
 * LOCKED MODULE NOTE (Lifecycle Closure #2): order paid transition now goes through OrderLifecycleEngine.
 */
class NmbPaymentCompletionService
{
    public function __construct(
        private readonly ProfitEngine $profitEngine,
        private readonly FulfillmentEngine $fulfillmentEngine,
        private readonly OrderLifecycleEngine $lifecycle,
    ) {}

    public function complete(Payment $payment): NmbPaymentCompletionResult
    {
        $this->validatePaymentMethod($payment);

        return DB::transaction(function () use ($payment): NmbPaymentCompletionResult {
            /** @var Payment $lockedPayment */
            $lockedPayment = Payment::query()
                ->whereKey($payment->id)
                ->lockForUpdate()
                ->firstOrFail();

            /** @var Order $order */
            $order = Order::query()
                ->whereKey($lockedPayment->order_id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($this->isAlreadyCompleted($lockedPayment)) {
                $this->startFulfillment($order);

                return new NmbPaymentCompletionResult(
                    completed: true,
                    alreadyCompleted: true,
                    message: 'NMB payment has already been completed.',
                    paymentId: $lockedPayment->id,
                    orderId: $order->id,
                );
            }

            $this->validateCanComplete($lockedPayment, $order);
            $this->markCompleted($lockedPayment, $order);

            $paidOrder = $order->fresh() ?? $order;
            try {
                $this->profitEngine->calculateForOrder($paidOrder);
            } catch (\Throwable $e) {
                Log::warning('profit.calculate_after_nmb_payment_failed', [
                    'order_id' => $paidOrder->id,
                    'message' => $e->getMessage(),
                ]);
            }

            $this->startFulfillment($paidOrder);

            return new NmbPaymentCompletionResult(
                completed: true,
                alreadyCompleted: false,
                message: 'NMB payment completed successfully.',
                paymentId: $lockedPayment->id,
                orderId: $order->id,
            );
        });
    }

    private function startFulfillment(Order $order): void
    {
        try {
            $this->fulfillmentEngine->createForOrder($order->fresh() ?? $order);
        } catch (\Throwable $e) {
            Log::warning('fulfillment.create_after_legacy_nmb_payment_failed', [
                'order_id' => $order->id,
                'message' => $e->getMessage(),
            ]);
        }
    }

    private function validatePaymentMethod(Payment $payment): void
    {
        if (! in_array($payment->method, [PaymentMethod::Nmb, PaymentMethod::BankTransfer], true)) {
            $this->throwValidationError('Payment is not an NMB payment.');
        }
    }

    private function isAlreadyCompleted(Payment $payment): bool
    {
        if ($payment->status === PaymentStatus::Paid) {
            return true;
        }

        return filled($payment->metadata['nmb_completion']['completed_at'] ?? null);
    }

    private function validateCanComplete(Payment $payment, Order $order): void
    {
        if (! ($payment->metadata['nmb_verification']['verified'] ?? false)) {
            $this->throwValidationError('Payment must be verified before completion.');
        }

        if (! in_array($payment->status, [PaymentStatus::Initiated, PaymentStatus::Pending], true)) {
            $this->throwValidationError("Payment cannot be completed while status is {$payment->status->value}.");
        }

        if (! in_array($order->status, [OrderStatus::Pending, OrderStatus::PendingPayment], true)) {
            $this->throwValidationError("Order cannot be completed while status is {$order->status->value}.");
        }

        if ($order->paid_at !== null) {
            $this->throwValidationError('Order has already been paid.');
        }

        if (bccomp((string) $payment->amount, (string) $order->total, 2) !== 0) {
            $this->throwValidationError('Payment amount must equal the order total.');
        }

        $hasOtherPaidPayment = Payment::query()
            ->where('order_id', $order->id)
            ->whereKeyNot($payment->id)
            ->where('status', PaymentStatus::Paid)
            ->exists();

        if ($hasOtherPaidPayment) {
            $this->throwValidationError('Another paid payment already exists for this order.');
        }
    }

    private function markCompleted(Payment $payment, Order $order): void
    {
        $metadata = $payment->metadata ?? [];
        $verification = is_array($metadata['nmb_verification'] ?? null) ? $metadata['nmb_verification'] : [];
        $completedAt = now();

        $payment->update([
            'status' => PaymentStatus::Paid,
            'paid_at' => $completedAt,
            'metadata' => array_merge($metadata, [
                'nmb_completion' => [
                    'completed_at' => $completedAt->toIso8601String(),
                    'source' => 'verification',
                    'transaction_id' => $verification['transaction_id'] ?? $payment->transaction_id,
                    'order_id' => $order->id,
                ],
            ]),
        ]);

        $this->lifecycle->markPaid(
            $order,
            OrderLifecycleContext::payment(
                'Legacy NMB payment verified',
                'nmb-payment:'.$payment->id,
                ['payment_id' => $payment->id],
            ),
        );
    }

    private function throwValidationError(string $message): never
    {
        throw ValidationException::withMessages([
            'payment' => [$message],
        ]);
    }
}
