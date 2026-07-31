<?php

namespace App\Actions\AdminOrders;

use App\Enums\OrderStatus;
use App\Models\Admin;
use App\Models\Order;
use App\Services\Inventory\OrderInventoryRestockService;
use App\Services\Orders\Lifecycle\OrderLifecycleContext;
use App\Services\Orders\Lifecycle\OrderLifecycleEngine;
use App\Services\Orders\OrderCancellationCascadeService;
use App\Services\Returns\RefundEngine;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Admin order cancellation — routed through OrderLifecycleEngine.
 * Inventory restore / hold release via OrderInventoryRestockService (ADR-055 / 2A-3C-3).
 */
class CancelOrderAction
{
    public function __construct(
        private readonly OrderLifecycleEngine $lifecycle,
        private readonly RefundEngine $refunds,
        private readonly OrderInventoryRestockService $inventoryRestock,
        private readonly OrderCancellationCascadeService $cancellationCascade,
    ) {}

    public function handle(Order $order, ?string $reason = null): Order
    {
        /** @var Admin|null $admin */
        $admin = Auth::user() instanceof Admin ? Auth::user() : null;

        return DB::transaction(function () use ($order, $reason, $admin): Order {
            $before = $order->fresh() ?? $order;
            $priorStatus = $before->status instanceof OrderStatus
                ? $before->status
                : OrderStatus::tryFrom((string) $before->status);

            $context = $admin !== null
                ? OrderLifecycleContext::admin($admin, 'admin_cancel', $reason ?? 'Admin cancelled order')
                : OrderLifecycleContext::system('admin_cancel', $reason ?? 'Admin cancelled order');

            $updated = $this->lifecycle->cancel($before, $context);

            if ($priorStatus !== null) {
                $this->inventoryRestock->applyAfterCancel($updated, $priorStatus, $admin);
            }

            $this->cancellationCascade->cascadeAfterOrderCancellation(
                $updated,
                $priorStatus,
                $admin,
                $reason,
            );

            $fresh = $updated->fresh() ?? $updated;
            $now = $fresh->status instanceof OrderStatus
                ? $fresh->status
                : OrderStatus::tryFrom((string) $fresh->status);

            if ($now === OrderStatus::RefundPending) {
                $this->refunds->ensureCancellationRefundPending($fresh, $admin);
            }

            return $fresh->fresh()->load([
                'user',
                'items.product.inventory',
                'items.variant.inventory',
                'fulfillment',
                'statusHistory',
                'refundTransactions',
            ]);
        });
    }
}
