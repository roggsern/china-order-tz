<?php

namespace App\Services\Orders;

use App\Enums\FulfillmentStatus;
use App\Enums\FulfillmentStatusHistorySource;
use App\Enums\NotificationEventType;
use App\Enums\OrderStatus;
use App\Enums\WarehouseJobStatus;
use App\Models\Admin;
use App\Models\Order;
use App\Services\Fulfillment\FulfillmentEngine;
use App\Services\Fulfillment\FulfillmentStatusUpdateContext;
use App\Services\Notifications\NotificationPlatform;
use App\Services\Warehouse\WarehouseEngine;
use Illuminate\Support\Facades\Log;

/**
 * Shared order-cancellation cascade — fulfilment, warehouse, and customer communication.
 * All fulfilment cancellation routes through FulfillmentEngine::updateStatus().
 */
class OrderCancellationCascadeService
{
    public function __construct(
        private readonly FulfillmentEngine $fulfillmentEngine,
        private readonly WarehouseEngine $warehouseEngine,
        private readonly NotificationPlatform $notifications,
    ) {}

    public function cascadeAfterOrderCancellation(
        Order $order,
        ?OrderStatus $priorStatus,
        ?Admin $admin = null,
        ?string $reason = null,
    ): void {
        if ($priorStatus !== null
            && in_array($priorStatus, [OrderStatus::Paid, OrderStatus::Confirmed, OrderStatus::Processing], true)
        ) {
            $this->cancelOpenFulfillment($order, $admin, $reason);
            $this->cancelActiveWarehouseJob($order, $reason);
        }

        $this->notifyOrderCancelled($order, $reason);
    }

    private function cancelOpenFulfillment(Order $order, ?Admin $admin, ?string $reason): void
    {
        $order->loadMissing('fulfillment');
        $fulfillment = $order->fulfillment;
        if ($fulfillment === null) {
            return;
        }

        $status = $fulfillment->status instanceof FulfillmentStatus
            ? $fulfillment->status
            : FulfillmentStatus::tryFrom((string) $fulfillment->status);

        if ($status === null || $status->isTerminal()) {
            return;
        }

        if (! $status->canTransitionTo(FulfillmentStatus::Cancelled)) {
            return;
        }

        $this->fulfillmentEngine->updateStatus($fulfillment, [
            'status' => FulfillmentStatus::Cancelled->value,
            'notes' => $reason,
        ], new FulfillmentStatusUpdateContext(
            source: FulfillmentStatusHistorySource::OrderCancel,
            admin: $admin,
            notes: $reason ?? 'Order cancelled',
        ));
    }

    private function cancelActiveWarehouseJob(Order $order, ?string $reason): void
    {
        $order->loadMissing(['fulfillment.warehouseJob', 'warehouseJob']);
        $job = $order->fulfillment?->warehouseJob ?? $order->warehouseJob;
        if ($job === null) {
            return;
        }

        $status = $job->status instanceof WarehouseJobStatus
            ? $job->status
            : WarehouseJobStatus::tryFrom((string) ($job->status ?? ''));

        if ($status === null || $status->isTerminal()) {
            return;
        }

        if (! $status->canTransitionTo(WarehouseJobStatus::Cancelled)) {
            return;
        }

        $this->warehouseEngine->updateStatus($job, [
            'status' => WarehouseJobStatus::Cancelled->value,
            'notes' => $reason ?? 'Order cancelled',
        ]);
    }

    private function notifyOrderCancelled(Order $order, ?string $reason): void
    {
        $order->loadMissing('user');
        $user = $order->user;
        if ($user === null) {
            return;
        }

        try {
            $this->notifications->notifyCustomer(
                NotificationEventType::OrderCancelled,
                $user,
                [
                    'customer_name' => $user->name,
                    'order_number' => $order->order_number,
                    'order_id' => $order->id,
                    'cancellation_reason' => $reason,
                ],
                idempotencyKey: 'order-cancelled:'.$order->id,
            );
        } catch (\Throwable $e) {
            Log::warning('orders.notify_cancelled_failed', [
                'order_id' => $order->id,
                'message' => $e->getMessage(),
            ]);
        }
    }
}
