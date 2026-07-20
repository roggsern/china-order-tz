<?php

namespace App\Actions\CustomerOrders;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;
use App\Services\Orders\Lifecycle\OrderLifecycleContext;
use App\Services\Orders\Lifecycle\OrderLifecycleEngine;
use App\Services\Returns\RefundEngine;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CancelCustomerOrderAction
{
    public function __construct(
        private readonly OrderLifecycleEngine $lifecycle,
        private readonly RefundEngine $refunds,
    ) {}

    public function handle(User $user, Order $order, ?string $reason = null): Order
    {
        if ($order->user_id !== $user->id) {
            abort(404);
        }

        if (! $this->lifecycle->customerMayCancel($order)) {
            throw ValidationException::withMessages([
                'order' => ['This order can no longer be cancelled.'],
            ]);
        }

        return DB::transaction(function () use ($user, $order, $reason): Order {
            $updated = $this->lifecycle->cancel(
                $order,
                OrderLifecycleContext::customer(
                    $user,
                    'customer_cancel',
                    $reason ?? 'Customer cancelled order',
                ),
            );

            $fresh = $updated->fresh() ?? $updated;
            $status = $fresh->status instanceof OrderStatus
                ? $fresh->status
                : OrderStatus::tryFrom((string) $fresh->status);

            if ($status === OrderStatus::RefundPending) {
                $this->refunds->ensureCancellationRefundPending($fresh, null);
            }

            return $fresh->load(['statusHistory', 'fulfillment', 'items', 'payments', 'items.product.supplier', 'refundTransactions']);
        });
    }
}
