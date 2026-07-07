<?php

namespace App\Actions\AdminShipments;

use App\Actions\Notifications\CreateNotificationAction;
use App\Enums\NotificationType;
use App\Enums\ShipmentStatus;
use App\Models\Admin;
use App\Models\Order;
use App\Models\ShipmentStatusHistory;
use App\Shipments\OrderShipmentStatusResolver;
use App\Shipments\ShipmentStatusTransitionValidator;
use Illuminate\Support\Facades\DB;

class UpdateShipmentStatusAction
{
    public function __construct(
        private readonly OrderShipmentStatusResolver $statusResolver,
        private readonly ShipmentStatusTransitionValidator $transitionValidator,
        private readonly CreateNotificationAction $createNotificationAction,
    ) {}

    public function handle(Order $order, Admin $admin, ShipmentStatus $targetStatus): Order
    {
        $currentStatus = $this->statusResolver->resolve($order);

        $this->transitionValidator->validate($currentStatus, $targetStatus);

        return DB::transaction(function () use ($order, $admin, $currentStatus, $targetStatus) {
            ShipmentStatusHistory::query()->create([
                'order_id' => $order->id,
                'admin_id' => $admin->id,
                'previous_status' => $currentStatus->value,
                'new_status' => $targetStatus->value,
            ]);

            $order->update([
                'shipment_status' => $targetStatus,
                'shipment_status_updated_at' => now(),
            ]);

            $order->loadMissing('user');

            if ($order->user !== null) {
                $this->createNotificationAction->handle(
                    user: $order->user,
                    type: NotificationType::ShipmentStatusUpdated,
                    title: 'Shipment Update',
                    message: sprintf(
                        'Your order %s has been updated to: %s.',
                        $order->order_number,
                        $targetStatus->label(),
                    ),
                    data: [
                        'order_id' => $order->id,
                        'order_number' => $order->order_number,
                        'previous_status' => $currentStatus->value,
                        'new_status' => $targetStatus->value,
                    ],
                );
            }

            return $order->fresh();
        });
    }
}
