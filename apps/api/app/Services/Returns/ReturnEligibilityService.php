<?php

namespace App\Services\Returns;

use App\Enums\OrderStatus;
use App\Enums\ShipmentLifecycleStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ReturnRequest;
use Illuminate\Validation\ValidationException;

/**
 * Determines whether an order (and its items) may enter the return workflow.
 */
class ReturnEligibilityService
{
    /**
     * @return array{eligible: bool, reason: string|null}
     */
    public function evaluate(Order $order): array
    {
        $order->loadMissing(['shipments', 'fulfillment']);

        $status = $order->status instanceof OrderStatus
            ? $order->status
            : OrderStatus::tryFrom((string) $order->status);

        if ($status === OrderStatus::Cancelled) {
            return ['eligible' => false, 'reason' => 'Cancelled orders cannot be returned.'];
        }

        if (in_array($status, [OrderStatus::Pending, OrderStatus::PendingPayment], true)) {
            return ['eligible' => false, 'reason' => 'Orders pending payment cannot be returned.'];
        }

        if ($status === OrderStatus::Refunded) {
            return ['eligible' => false, 'reason' => 'Fully refunded orders cannot be returned again.'];
        }

        if (! $this->isDelivered($order, $status)) {
            return ['eligible' => false, 'reason' => 'Only delivered orders can be returned.'];
        }

        if ($order->fulfillment === null && $order->shipments->isEmpty()) {
            return ['eligible' => false, 'reason' => 'Unfulfilled orders cannot be returned.'];
        }

        $openReturn = ReturnRequest::query()
            ->where('order_id', $order->id)
            ->whereNotIn('status', ['rejected', 'cancelled', 'completed'])
            ->exists();

        if ($openReturn) {
            return ['eligible' => false, 'reason' => 'This order already has an open return request.'];
        }

        return ['eligible' => true, 'reason' => null];
    }

    public function assertEligible(Order $order): void
    {
        $result = $this->evaluate($order);
        if (! $result['eligible']) {
            throw ValidationException::withMessages([
                'order' => [$result['reason'] ?? 'Order is not eligible for return.'],
            ]);
        }
    }

    public function assertItemBelongs(Order $order, OrderItem $item, int $quantity): void
    {
        if ($item->order_id !== $order->id) {
            throw ValidationException::withMessages([
                'items' => ['Return item does not belong to this order.'],
            ]);
        }

        if ($quantity < 1 || $quantity > (int) $item->quantity) {
            throw ValidationException::withMessages([
                'items' => ["Invalid return quantity for order item {$item->id}."],
            ]);
        }

        $alreadyReturned = (int) \App\Models\ReturnItem::query()
            ->where('order_item_id', $item->id)
            ->whereHas('returnRequest', function ($q) {
                $q->whereNotIn('status', ['rejected', 'cancelled']);
            })
            ->sum('quantity');

        if ($alreadyReturned + $quantity > (int) $item->quantity) {
            throw ValidationException::withMessages([
                'items' => ['Return quantity exceeds remaining returnable quantity.'],
            ]);
        }
    }

    private function isDelivered(Order $order, ?OrderStatus $status): bool
    {
        if (in_array($status, [OrderStatus::Delivered, OrderStatus::Completed], true)) {
            return true;
        }

        foreach ($order->shipments as $shipment) {
            if ($shipment->delivered_at !== null) {
                return true;
            }

            $shipmentStatus = $shipment->status instanceof ShipmentLifecycleStatus
                ? $shipment->status
                : ShipmentLifecycleStatus::tryFrom((string) $shipment->status);

            if ($shipmentStatus === ShipmentLifecycleStatus::Delivered) {
                return true;
            }
        }

        return false;
    }
}
