<?php

namespace App\Services\Purchasing;

use App\Models\CartItem;
use App\Models\Product;
use App\Services\Cart\CartProductPricingQuantity;
use App\Services\Purchasing\DTOs\PurchaseQuantityPresentation;

/**
 * Display-only purchase-quantity contract from PurchaseQuantityRule.
 * Read-only: does not mutate carts, products, prices, or shipping.
 * Does not reimplement legality arithmetic.
 */
final class PresentPurchaseQuantity
{
    public function present(Product $product, int $eligibleQuantity): ?PurchaseQuantityPresentation
    {
        $evaluation = PurchaseQuantityRule::forProduct($product)->evaluate($eligibleQuantity);
        if (! $evaluation->hasRestriction) {
            return null;
        }

        return new PurchaseQuantityPresentation(
            minimumQuantity: (int) $evaluation->minimumQuantity,
            increment: $evaluation->increment,
            eligibleQuantity: $evaluation->eligibleQuantity,
            aggregatesVariants: $this->aggregatesVariants($product),
            minimumSatisfied: $evaluation->minimumSatisfied,
            incrementSatisfied: $evaluation->incrementSatisfied,
            quantityToMinimum: $evaluation->quantityToMinimum,
            nextLegalQuantity: (int) $evaluation->nextLegalQuantity,
            constructionComplete: $evaluation->isLegal,
            blocksCheckout: ! $evaluation->isLegal,
        );
    }

    /**
     * One blocker per illegal product_id, in first-appearance cart-line order.
     *
     * @param  iterable<mixed>  $items
     * @return list<array{
     *     product_id: string,
     *     minimum_quantity: int|null,
     *     increment: int|null,
     *     eligible_quantity: int,
     *     minimum_satisfied: bool,
     *     increment_satisfied: bool,
     *     quantity_to_minimum: int,
     *     next_legal_quantity: int|null,
     *     blocks_checkout: bool
     * }>
     */
    public function blockersForCartItems(iterable $items): array
    {
        $ordered = collect($items)
            ->filter(fn (mixed $item): bool => $item instanceof CartItem)
            ->sortBy([
                ['created_at', 'asc'],
                ['id', 'asc'],
            ])
            ->values();

        $aggregates = CartProductPricingQuantity::mapByProductId($ordered);
        $seen = [];
        $blockers = [];

        foreach ($ordered as $item) {
            $productId = (string) $item->product_id;
            if (isset($seen[$productId])) {
                continue;
            }
            $seen[$productId] = true;

            $product = $item->relationLoaded('product') ? $item->product : null;
            if ($product === null) {
                continue;
            }

            $evaluation = PurchaseQuantityRule::forProduct($product)->evaluate(
                $aggregates[$productId] ?? max(0, (int) $item->quantity),
            );
            if (! $evaluation->hasRestriction || $evaluation->isLegal) {
                continue;
            }

            $blockers[] = $evaluation->toCheckoutErrorPayload();
        }

        return $blockers;
    }

    /**
     * Capability: same-product sibling SKUs can contribute to cart eligible quantity.
     * True when the product currently has more than one active variant — not whether
     * this response already contains multiple lines.
     */
    private function aggregatesVariants(Product $product): bool
    {
        if (isset($product->variants_count)) {
            return (int) $product->variants_count > 1;
        }

        if ($product->relationLoaded('variants')) {
            return $product->variants->where('is_active', true)->count() > 1;
        }

        return $product->variants()->where('is_active', true)->count() > 1;
    }
}
