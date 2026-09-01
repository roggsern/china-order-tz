<?php

namespace App\Services\Purchasing;

use App\Models\Product;

/**
 * Authoritative purchase-quantity legality for a product-level MOQ / increment.
 * Evaluates an already-aggregated same-product quantity. Never prices.
 */
final class PurchaseQuantityRule
{
    public function __construct(
        public readonly string $productId,
        public readonly ?int $minimumQuantity,
        public readonly ?int $increment,
    ) {}

    public static function forProduct(Product $product): self
    {
        $minimum = self::positiveOrNull($product->minimum_order_quantity);
        $increment = self::positiveOrNull($product->order_increment);

        // Increment without MOQ: fail-closed by treating increment as the effective minimum.
        if ($minimum === null && $increment !== null) {
            $minimum = $increment;
        }

        return new self((string) $product->id, $minimum, $increment);
    }

    public function hasRestriction(): bool
    {
        return $this->minimumQuantity !== null;
    }

    public function evaluate(int $eligibleQuantity): PurchaseQuantityEvaluation
    {
        $qty = max(0, $eligibleQuantity);

        if ($this->minimumQuantity === null) {
            return new PurchaseQuantityEvaluation(
                productId: $this->productId,
                minimumQuantity: null,
                increment: null,
                eligibleQuantity: $qty,
                minimumSatisfied: true,
                incrementSatisfied: true,
                isLegal: true,
                quantityToMinimum: 0,
                nextLegalQuantity: $qty > 0 ? $qty : 1,
                hasRestriction: false,
            );
        }

        $minimum = $this->minimumQuantity;
        $increment = $this->increment;
        $minimumSatisfied = $qty >= $minimum;
        $incrementSatisfied = $increment === null
            ? true
            : ($minimumSatisfied && (($qty - $minimum) % $increment === 0));
        $isLegal = $minimumSatisfied && $incrementSatisfied;

        return new PurchaseQuantityEvaluation(
            productId: $this->productId,
            minimumQuantity: $minimum,
            increment: $increment,
            eligibleQuantity: $qty,
            minimumSatisfied: $minimumSatisfied,
            incrementSatisfied: $incrementSatisfied,
            isLegal: $isLegal,
            quantityToMinimum: max(0, $minimum - $qty),
            nextLegalQuantity: $this->nextLegalQuantity($qty, $minimum, $increment),
            hasRestriction: true,
        );
    }

    private static function positiveOrNull(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        $int = (int) $value;

        return $int >= 1 ? $int : null;
    }

    private function nextLegalQuantity(int $qty, int $minimum, ?int $increment): int
    {
        if ($qty < $minimum) {
            return $minimum;
        }

        if ($increment === null) {
            return $qty;
        }

        $offset = ($qty - $minimum) % $increment;
        if ($offset === 0) {
            return $qty;
        }

        return $qty + ($increment - $offset);
    }
}
