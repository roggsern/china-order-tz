<?php

namespace App\Actions\AdminOrders;

use App\Enums\OrderStatus;
use App\Models\Admin;
use App\Models\Order;
use App\Models\RefundTransaction;
use App\Services\Returns\RefundEngine;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * Manual launch workflow: complete a cancellation refund (refund_pending → refunded).
 * No automatic PSP refund — admin confirms money was returned offline.
 */
class CompleteCancellationRefundAction
{
    public function __construct(
        private readonly RefundEngine $refunds,
    ) {}

    /**
     * @param  array{
     *     amount: float|int|string,
     *     reference: string,
     *     notes?: string|null,
     *     reason?: string|null,
     *     confirm: bool
     * }  $input
     */
    public function handle(Order $order, array $input): RefundTransaction
    {
        /** @var Admin|null $admin */
        $admin = Auth::user() instanceof Admin ? Auth::user() : null;
        if ($admin === null) {
            throw ValidationException::withMessages([
                'admin' => ['Only authenticated admins may complete cancellation refunds.'],
            ]);
        }

        if (! ($input['confirm'] ?? false)) {
            throw ValidationException::withMessages([
                'confirm' => ['Explicit confirmation is required to complete a refund.'],
            ]);
        }

        $status = $order->status instanceof OrderStatus
            ? $order->status
            : OrderStatus::tryFrom((string) $order->status);

        if ($status === OrderStatus::Refunded) {
            $completed = $order->refundTransactions()
                ->whereNull('return_request_id')
                ->where('status', \App\Enums\RefundTransactionStatus::Completed)
                ->latest()
                ->first();

            if ($completed !== null) {
                return $completed;
            }
        }

        if ($status !== OrderStatus::RefundPending) {
            throw ValidationException::withMessages([
                'order' => ['Only orders in refund_pending can complete a cancellation refund.'],
            ]);
        }

        return $this->refunds->completeCancellationRefund($order, $input, $admin);
    }
}
