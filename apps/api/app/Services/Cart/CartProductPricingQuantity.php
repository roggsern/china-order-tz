<?php

namespace App\Services\Cart;

use App\Models\CartItem;

/**
 * Same-product_id quantity map for cart lines.
 *
 * Used as aggregation infrastructure for volume-tier eligibility and for
 * purchase-quantity (MOQ / increment) legality. Those meanings stay separate:
 * this class only sums quantities.
 *
 * Inventory, shipping, reservations, and fulfillment remain per line / SKU.
 * CommercePricingResolver must not query cart state — callers pass the map
 * through as the resolver's tier-eligibility quantity.
 */
final class CartProductPricingQuantity
{
    /**
     * @param  iterable<CartItem>  $items
     * @return array<string, int> product_id => combined quantity
     */
    public static function mapByProductId(iterable $items): array
    {
        $map = [];

        foreach ($items as $item) {
            if (! $item instanceof CartItem) {
                continue;
            }

            $productId = (string) $item->product_id;
            $map[$productId] = ($map[$productId] ?? 0) + max(0, (int) $item->quantity);
        }

        return $map;
    }

    /**
     * @param  iterable<CartItem>  $items
     */
    public static function forProduct(iterable $items, string $productId): int
    {
        $total = 0;

        foreach ($items as $item) {
            if (! $item instanceof CartItem) {
                continue;
            }

            if ((string) $item->product_id !== $productId) {
                continue;
            }

            $total += max(0, (int) $item->quantity);
        }

        return $total;
    }
}
