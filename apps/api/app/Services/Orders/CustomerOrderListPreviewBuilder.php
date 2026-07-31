<?php

namespace App\Services\Orders;

use App\Models\Order;

/**
 * Builds lightweight list-card preview data from immutable order item snapshots.
 */
class CustomerOrderListPreviewBuilder
{
    /**
     * @return array{
     *     item_count: int,
     *     total_quantity: int,
     *     primary_item: array{name: string, image_url: string|null, quantity: int}|null,
     *     extra_items: int
     * }
     */
    public function build(Order $order): array
    {
        $items = $order->relationLoaded('items')
            ? $order->items
            : collect();

        $itemCount = $items->count();
        $totalQuantity = (int) $items->sum('quantity');
        $primary = $items->first();
        $extraItems = max(0, $itemCount - 1);

        return [
            'item_count' => $itemCount,
            'total_quantity' => $totalQuantity,
            'primary_item' => $primary === null ? null : [
                'name' => (string) ($primary->product_name_snapshot ?? $primary->product_name),
                'image_url' => $primary->product_image_snapshot ?? $primary->image_snapshot,
                'quantity' => (int) $primary->quantity,
            ],
            'extra_items' => $extraItems,
        ];
    }
}
