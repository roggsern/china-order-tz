<?php

namespace App\Services\Purchasing\DTOs;

/**
 * Customer-facing purchase-quantity (MOQ / increment) presentation.
 * Does not calculate payable price — PurchaseQuantityRule remains legality authority.
 */
final class PurchaseQuantityPresentation
{
    public function __construct(
        public readonly int $minimumQuantity,
        public readonly ?int $increment,
        public readonly int $eligibleQuantity,
        public readonly bool $aggregatesVariants,
        public readonly bool $minimumSatisfied,
        public readonly bool $incrementSatisfied,
        public readonly int $quantityToMinimum,
        public readonly int $nextLegalQuantity,
        public readonly bool $constructionComplete,
        public readonly bool $blocksCheckout,
    ) {}

    /**
     * @return array{
     *     minimum_quantity: int,
     *     increment: int|null,
     *     eligible_quantity: int,
     *     aggregates_variants: bool,
     *     minimum_satisfied: bool,
     *     increment_satisfied: bool,
     *     quantity_to_minimum: int,
     *     next_legal_quantity: int,
     *     construction_complete: bool,
     *     blocks_checkout: bool
     * }
     */
    public function toArray(): array
    {
        return [
            'minimum_quantity' => $this->minimumQuantity,
            'increment' => $this->increment,
            'eligible_quantity' => $this->eligibleQuantity,
            'aggregates_variants' => $this->aggregatesVariants,
            'minimum_satisfied' => $this->minimumSatisfied,
            'increment_satisfied' => $this->incrementSatisfied,
            'quantity_to_minimum' => $this->quantityToMinimum,
            'next_legal_quantity' => $this->nextLegalQuantity,
            'construction_complete' => $this->constructionComplete,
            'blocks_checkout' => $this->blocksCheckout,
        ];
    }
}
