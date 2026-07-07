<?php

namespace App\Actions\CustomerOrders;

use App\Models\Order;
use App\Models\User;
use App\Shipments\OrderShipmentStatusResolver;
use App\Shipments\ShipmentTimelineBuilder;

class ShowShipmentTrackingAction
{
    public function __construct(
        private readonly OrderShipmentStatusResolver $statusResolver,
        private readonly ShipmentTimelineBuilder $timelineBuilder,
    ) {}

    /**
     * @return array{
     *     order_number: string,
     *     current_status: string,
     *     timeline: list<array{
     *         step: string,
     *         completed: bool,
     *         completed_at: \Illuminate\Support\Carbon|null,
     *         description: string
     *     }>
     * }
     */
    public function handle(Order $order, User $user): array
    {
        if ($order->user_id !== $user->id) {
            abort(404);
        }

        $order->loadMissing(['shipmentStatusHistories']);

        $currentStatus = $this->statusResolver->resolve($order);

        return [
            'order_number' => $order->order_number,
            'current_status' => $currentStatus->value,
            'timeline' => $this->timelineBuilder->build($currentStatus, $order),
        ];
    }
}
