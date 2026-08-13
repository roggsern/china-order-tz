<?php

namespace App\Actions\CustomerOrders;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;
use App\Services\Inventory\OrderInventoryRestockService;
use App\Services\Orders\Lifecycle\OrderLifecycleContext;
use App\Services\Orders\Lifecycle\OrderLifecycleEngine;
use App\Services\Orders\OrderCancellationCascadeService;
use App\Services\Returns\RefundEngine;
use App\Support\Http\ApiResponse;
use Illuminate\Support\Facades\DB;

class CancelCustomerOrderAction
{
    public function __construct(
        private readonly OrderLifecycleEngine $lifecycle,
        private readonly RefundEngine $refunds,
        private readonly OrderInventoryRestockService $inventoryRestock,
        private readonly OrderCancellationCascadeService $cancellationCascade,
    ) {}

    public function handle(User $user, Order $order, ?string $reason = null): Order
    {
        if ($order->user_id !== $user->id) {
            abort(404);
        }

        if (! $this->lifecycle->customerMayCancel($order)) {
            ApiResponse::throwCodedValidation([
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

            $this->cancellationCascade->cascadeAfterOrderCancellation(
                $updated,
                $priorStatus,
                null,
                $reason,
            );

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
