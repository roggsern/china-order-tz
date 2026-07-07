<?php

namespace App\Actions\CustomerOrders;

use App\Enums\OrderStatus;
use App\Enums\ShipmentStatus;
use App\Models\Order;
use App\Models\User;
use App\Shipments\ShipmentTimelineBuilder;

class ShowShipmentTrackingAction
{
    public function __construct(
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

        $currentStatus = $this->resolveCurrentStatus($order);

        return [
            'order_number' => $order->order_number,
            'current_status' => $currentStatus->value,
            'timeline' => $this->timelineBuilder->build($currentStatus, $order),
        ];
    }

    private function resolveCurrentStatus(Order $order): ShipmentStatus
    {
        return match ($order->status) {
            OrderStatus::Pending => ShipmentStatus::OrderReceived,
            OrderStatus::Paid => ShipmentStatus::PaymentConfirmed,
            OrderStatus::Confirmed => ShipmentStatus::SupplierProcessing,
            OrderStatus::Processing => ShipmentStatus::ArrivedChinaWarehouse,
            OrderStatus::Shipped => ShipmentStatus::OutForDelivery,
            OrderStatus::Delivered => ShipmentStatus::Delivered,
            OrderStatus::Cancelled, OrderStatus::Refunded => ShipmentStatus::OrderReceived,
        };
    }
}
