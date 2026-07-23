<?php

namespace App\Actions\CustomerOrders;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;
use App\Services\Inventory\OrderInventoryRestockService;
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
        private readonly OrderInventoryRestockService $inventoryRestock,
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
            $before = $order->fresh() ?? $order;
            $priorStatus = $before->status instanceof OrderStatus
                ? $before->status
                : OrderStatus::tryFrom((string) $before->status);

            $updated = $this->lifecycle->cancel(
                $before,
                OrderLifecycleContext::customer(
                    $user,
                    'customer_cancel',
                    $reason ?? 'Customer cancelled order',
                ),
            );

            if ($priorStatus !== null) {
                $this->inventoryRestock->applyAfterCancel($updated, $priorStatus, null);
            }

            $fresh = $updated->fresh() ?? $updated;
            $status = $fresh->status instanceof OrderStatus
                ? $fresh->status
                : OrderStatus::tryFrom((string) $fresh->status);

            if ($status === OrderStatus::RefundPending) {
                $this->refunds->ensureCancellationRefundPending($fresh, null);
            }

            return $fresh->load(['statusHistory', 'fulfillment', 'items', 'payments', 'items.product.commerceChannel', 'refundTransactions']);
        });
    }
}
