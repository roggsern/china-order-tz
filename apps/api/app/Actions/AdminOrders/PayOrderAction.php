<?php

namespace App\Actions\AdminOrders;

use App\Enums\OrderStatus;
use App\Models\Admin;
use App\Models\Order;
use App\Services\Orders\Lifecycle\OrderLifecycleContext;
use App\Services\Payments\ManualPaymentConfirmationService;
use App\Services\Payments\PaidOrderCompletionService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Admin payment confirmation.
 *
 * Pay at Office (cash) uses ManualPaymentConfirmationService so payment + order
 * converge into PaidOrderCompletionService. Orders without a cash payment keep
 * the generic admin-pay path through the same downstream completion service.
 */
class PayOrderAction
{
    public function __construct(
        private readonly ManualPaymentConfirmationService $manualConfirmation,
        private readonly PaidOrderCompletionService $paidCompletion,
    ) {}

    public function handle(
        Order $order,
        ?string $reference = null,
        ?string $note = null,
    ): Order {
        /** @var Admin|null $admin */
        $admin = Auth::user() instanceof Admin ? Auth::user() : null;
        if ($admin === null) {
            $this->throwValidationError('An authorized admin must confirm payment.');
        }

        if ($this->manualConfirmation->hasBlockingNonCashPayment($order)) {
            $this->throwValidationError('This order is not a Pay at Office payment.');
        }

        $cashPayment = $this->manualConfirmation->findCashPayment($order);
        if ($cashPayment !== null) {
            return $this->manualConfirmation->confirm($order, $admin, $reference, $note);
        }

        return $this->confirmGenericAdminPay($order, $admin);
    }

    private function confirmGenericAdminPay(Order $order, Admin $admin): Order
    {
        return DB::transaction(function () use ($order, $admin): Order {
            /** @var Order $locked */
            $locked = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            $status = $locked->status instanceof OrderStatus
                ? $locked->status
                : OrderStatus::tryFrom((string) $locked->status);

            if ($status === OrderStatus::Paid && $locked->paid_at !== null) {
                return $locked->fresh([
                    'user',
                    'coupon',
                    'items.product',
                    'items.variant',
                    'payments',
                    'shippingAddress',
                    'fulfillment',
                    'statusHistory',
                ]) ?? $locked;
            }

            if ($status === OrderStatus::Cancelled) {
                $this->throwValidationError('Cancelled orders cannot be confirmed as paid.');
            }

            if (in_array($status, [OrderStatus::Refunded, OrderStatus::RefundPending], true)) {
                $this->throwValidationError('Refunded orders cannot be confirmed as paid.');
            }

            if ($status === null || ! in_array($status, [OrderStatus::Pending, OrderStatus::PendingPayment], true)) {
                $this->throwValidationError('Only pending orders can be paid.');
            }

            $context = new OrderLifecycleContext(
                source: 'admin_pay',
                reason: 'Admin marked order paid',
                admin: $admin,
                idempotencyKey: 'admin-pay:'.$locked->id,
                metadata: ['path' => 'PayOrderAction'],
            );

            try {
                $paid = $this->paidCompletion->complete(
                    $locked,
                    $context,
                    inventorySource: 'admin_pay',
                    inventoryActor: $admin,
                    inventoryStrict: true,
                    inventoryMetadata: ['path' => 'PayOrderAction'],
                );
            } catch (ValidationException $e) {
                $message = collect($e->errors())->flatten()->first()
                    ?? 'Unable to confirm payment.';
                $this->throwValidationError((string) $message);
            }

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

    private function throwValidationError(string $message): never
    {
        $exception = ValidationException::withMessages([
            'order' => [$message],
        ]);

        $exception->response = response()->json([
            'success' => false,
            'message' => $message,
        ], 422);

        throw $exception;
    }
}
