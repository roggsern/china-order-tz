<?php

namespace App\Shipments;

use App\Enums\ShipmentStatus;
use App\Models\Order;
use Illuminate\Support\Carbon;

class ShipmentTimelineBuilder
{
    /**
     * @return list<array{
     *     step: string,
     *     completed: bool,
     *     completed_at: Carbon|null,
     *     description: string
     * }>
     */
    public function build(ShipmentStatus $currentStatus, Order $order): array
    {
        $timeline = [];
        $currentIndex = $this->currentIndex($currentStatus);

        foreach (ShipmentStatus::timeline() as $index => $status) {
            $completed = $index <= $currentIndex;

            $timeline[] = [
                'step' => $status->label(),
                'completed' => $completed,
                'completed_at' => $completed ? $this->completedAtFor($status, $order) : null,
                'description' => $status->description(),
            ];
        }

        return $timeline;
    }

    private function currentIndex(ShipmentStatus $currentStatus): int
    {
        $index = array_search($currentStatus, ShipmentStatus::timeline(), true);

        return $index === false ? 0 : $index;
    }

    private function completedAtFor(ShipmentStatus $status, Order $order): ?Carbon
    {
        return match ($status) {
            ShipmentStatus::OrderReceived => $order->placed_at ?? $order->created_at,
            ShipmentStatus::PaymentConfirmed => $order->paid_at,
            ShipmentStatus::Delivered => $order->status->value === 'delivered'
                ? ($order->updated_at ?? $order->paid_at)
                : null,
            default => null,
        };
    }
}
