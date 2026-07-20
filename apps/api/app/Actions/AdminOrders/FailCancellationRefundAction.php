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
 * Mark a cancellation refund attempt as failed — order stays refund_pending and recoverable.
 */
class FailCancellationRefundAction
{
    public function __construct(
        private readonly RefundEngine $refunds,
    ) {}

    /**
     * @param  array{notes?: string|null, reason?: string|null}  $input
     */
    public function handle(Order $order, array $input = []): RefundTransaction
    {
        /** @var Admin|null $admin */
        $admin = Auth::user() instanceof Admin ? Auth::user() : null;
        if ($admin === null) {
            throw ValidationException::withMessages([
                'admin' => ['Only authenticated admins may fail cancellation refunds.'],
            ]);
        }

        $status = $order->status instanceof OrderStatus
            ? $order->status
            : OrderStatus::tryFrom((string) $order->status);

        if ($status !== OrderStatus::RefundPending) {
            throw ValidationException::withMessages([
                'order' => ['Only orders in refund_pending can fail a cancellation refund.'],
            ]);
        }

        return $this->refunds->failCancellationRefund($order, $input, $admin);
    }
}
