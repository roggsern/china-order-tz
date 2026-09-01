<?php

namespace App\Services\Purchasing;

/**
 * Purchase-lot evaluation for one product_id aggregate quantity.
 * Does not calculate payable price, shipping, or discounts.
 */
final class PurchaseQuantityEvaluation
{
    public function __construct(
        public readonly string $productId,
        public readonly ?int $minimumQuantity,
        public readonly ?int $increment,
        public readonly int $eligibleQuantity,
        public readonly bool $minimumSatisfied,
        public readonly bool $incrementSatisfied,
        public readonly bool $isLegal,
        public readonly int $quantityToMinimum,
        public readonly ?int $nextLegalQuantity,
        public readonly bool $hasRestriction,
    ) {}

    /**
     * @return array{
     *     product_id: string,
     *     minimum_quantity: int|null,
     *     increment: int|null,
     *     eligible_quantity: int,
     *     minimum_satisfied: bool,
     *     increment_satisfied: bool,
     *     quantity_to_minimum: int,
     *     next_legal_quantity: int|null,
     *     blocks_checkout: bool
     * }
     */
    public function toCheckoutErrorPayload(): array
    {
        return [
            'product_id' => $this->productId,
            'minimum_quantity' => $this->minimumQuantity,
            'increment' => $this->increment,
            'eligible_quantity' => $this->eligibleQuantity,
            'minimum_satisfied' => $this->minimumSatisfied,
            'increment_satisfied' => $this->incrementSatisfied,
            'quantity_to_minimum' => $this->quantityToMinimum,
            'next_legal_quantity' => $this->nextLegalQuantity,
            'blocks_checkout' => true,
        ];
    }
}
