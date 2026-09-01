<?php

namespace App\Services\Pricing\DTOs;

/**
 * Customer-facing volume / bulk pricing presentation.
 * Does not change payable unit price — CommercePricingResolver remains authority.
 *
 * @phpstan-type VolumeTier array{
 *     min_quantity: int,
 *     unit_price: string,
 *     type: string,
 *     discount_percent: string|null,
 *     scope: string
 * }
 */
final class VolumePricingPresentation
{
    /**
     * @param  VolumeTier|null  $currentTier
     * @param  VolumeTier|null  $nextTier
     * @param  list<VolumeTier>  $tiers
     */
    public function __construct(
        public readonly int $eligibleQuantity,
        public readonly bool $aggregatesVariants,
        public readonly ?array $currentTier,
        public readonly ?array $nextTier,
        public readonly ?int $quantityToNextTier,
        public readonly string $baseUnitPrice,
        public readonly string $resolvedUnitPrice,
        public readonly string $savingsPerUnit,
        public readonly string $savingsTotal,
        public readonly array $tiers,
        public readonly string $currency = 'TZS',
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'eligible_quantity' => $this->eligibleQuantity,
            'aggregates_variants' => $this->aggregatesVariants,
            'current_tier' => $this->currentTier,
            'next_tier' => $this->nextTier,
            'quantity_to_next_tier' => $this->quantityToNextTier,
            'base_unit_price' => $this->baseUnitPrice,
            'resolved_unit_price' => $this->resolvedUnitPrice,
            'savings_per_unit' => $this->savingsPerUnit,
            'savings_total' => $this->savingsTotal,
            'currency' => $this->currency,
            'tiers' => $this->tiers,
        ];
    }
}
