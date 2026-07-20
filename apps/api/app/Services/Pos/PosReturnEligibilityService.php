<?php

namespace App\Services\Pos;

use App\Enums\OrderStatus;
use App\Enums\SalesOrigin;
use App\Models\Admin;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ReturnItem;
use App\Models\ReturnRequest;
use App\Services\Stores\ActiveStoreContext;
use Illuminate\Validation\ValidationException;

/**
 * POS-specific return eligibility — paid POS orders at the active store.
 * Ecommerce delivery rules intentionally do not apply.
 */
class PosReturnEligibilityService
{
    public function __construct(
        private readonly ActiveStoreContext $storeContext,
    ) {}

    /**
     * @return array{eligible: bool, reason: string|null}
     */
    public function evaluate(Order $order, Admin $cashier): array
    {
        if ($order->sales_origin !== SalesOrigin::Pos) {
            return ['eligible' => false, 'reason' => 'Only POS sales can be returned through POS returns.'];
        }

        $status = $order->status instanceof OrderStatus
            ? $order->status
            : OrderStatus::tryFrom((string) $order->status);

        if ($status === OrderStatus::Cancelled) {
            return ['eligible' => false, 'reason' => 'Cancelled orders cannot be returned.'];
        }

        if (in_array($status, [OrderStatus::Pending, OrderStatus::PendingPayment], true)) {
            return ['eligible' => false, 'reason' => 'Unpaid orders cannot be returned.'];
        }

        if ($order->store_id === null) {
            return ['eligible' => false, 'reason' => 'POS order has no store.'];
        }

        try {
            $this->storeContext->assertCanAccess($cashier, $order->store()->firstOrFail());
        } catch (ValidationException) {
            return ['eligible' => false, 'reason' => 'You are not assigned to this store.'];
        }

        $openReturn = ReturnRequest::query()
            ->where('order_id', $order->id)
            ->where('sales_origin', SalesOrigin::Pos->value)
            ->whereNotIn('status', ['rejected', 'cancelled', 'completed'])
            ->exists();

        if ($openReturn) {
            return ['eligible' => false, 'reason' => 'This order already has an open POS return.'];
        }

        $returnable = $this->returnableItems($order);
        if ($returnable === []) {
            return ['eligible' => false, 'reason' => 'No returnable quantity remains on this order.'];
        }

        return ['eligible' => true, 'reason' => null];
    }

    public function assertEligible(Order $order, Admin $cashier): void
    {
        $result = $this->evaluate($order, $cashier);
        if (! $result['eligible']) {
            throw ValidationException::withMessages([
                'order' => [$result['reason'] ?? 'Order is not eligible for POS return.'],
            ]);
        }
    }

    public function assertItemQuantity(Order $order, OrderItem $item, int $quantity): void
    {
        if ($item->order_id !== $order->id) {
            throw ValidationException::withMessages([
                'items' => ['Return item does not belong to this order.'],
            ]);
        }

        $remaining = $this->remainingQuantity($item);
        if ($quantity < 1 || $quantity > $remaining) {
            \App\Support\Pos\PosErrors::returnQuantityExceeded();
        }
    }

    public function remainingQuantity(OrderItem $item): int
    {
        $already = (int) ReturnItem::query()
            ->where('order_item_id', $item->id)
            ->whereHas('returnRequest', function ($q) {
                $q->whereNotIn('status', ['rejected', 'cancelled']);
            })
            ->sum('quantity');

        return max(0, (int) $item->quantity - $already);
    }

    /**
     * @return list<array{order_item: OrderItem, remaining_quantity: int}>
     */
    public function returnableItems(Order $order): array
    {
        $order->loadMissing('items');
        $rows = [];
        foreach ($order->items as $item) {
            $remaining = $this->remainingQuantity($item);
            if ($remaining > 0) {
                $rows[] = [
                    'order_item' => $item,
                    'remaining_quantity' => $remaining,
                ];
            }
        }

        return $rows;
    }
}
