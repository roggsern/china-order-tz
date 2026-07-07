<?php

namespace App\Actions\AdminShipments;

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

            return $order->fresh();
        });
    }
}
