<?php

namespace App\Listeners\Warehouse;

use App\Enums\NotificationEventType;
use App\Events\Warehouse\PickCompleted;
use App\Events\Warehouse\PickStarted;
use App\Services\Notifications\NotificationPlatform;
use Illuminate\Support\Facades\Log;

class HandleWarehouseOperations
{
    public function __construct(
        private readonly NotificationPlatform $notifications,
    ) {}

    public function onPickStarted(PickStarted $event): void
    {
        $pickList = $event->pickList->loadMissing(['order.user', 'warehouseJob']);
        $customer = $pickList->order?->user;

        if ($customer === null) {
            return;
        }

        try {
            $this->notifications->notifyCustomer(
                NotificationEventType::WarehousePickAssigned,
                $customer,
                [
                    'customer_name' => $customer->name,
                    'order_number' => $pickList->order?->order_number,
                    'order_id' => $pickList->order_id,
                    'warehouse_job_id' => $pickList->warehouse_job_id,
                ],
                idempotencyKey: 'warehouse-pick-assigned:'.$pickList->id,
            );
        } catch (\Throwable $e) {
            Log::warning('warehouse.notify_pick_assigned_failed', [
                'pick_list_id' => $pickList->id,
                'message' => $e->getMessage(),
            ]);
        }
    }

    public function onPickCompleted(PickCompleted $event): void
    {
        $pickList = $event->pickList->loadMissing(['order.user']);
        $customer = $pickList->order?->user;

        if ($customer === null) {
            return;
        }

        try {
            $this->notifications->notifyCustomer(
                NotificationEventType::WarehousePickCompleted,
                $customer,
                [
                    'customer_name' => $customer->name,
                    'order_number' => $pickList->order?->order_number,
                    'order_id' => $pickList->order_id,
                ],
                idempotencyKey: 'warehouse-pick-completed:'.$pickList->id,
            );
        } catch (\Throwable $e) {
            Log::warning('warehouse.notify_pick_completed_failed', [
                'pick_list_id' => $pickList->id,
                'message' => $e->getMessage(),
            ]);
        }
    }
}
