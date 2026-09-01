<?php

namespace App\Services\Pricing\DTOs;

final class PriceBreakdown
{
    /**
     * @param  list<PriceStageResult>  $stages
     * @param  array<string, mixed>|null  $volumePricing
     */
    public function __construct(
        public readonly string $productId,
        public readonly ?string $configurationId,
        public readonly int $quantity,
        public readonly string $currency,
        public readonly string $unitPrice,
        public readonly string $lineTotal,
        public readonly array $stages,
        public readonly ?array $volumePricing = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'product_id' => $this->productId,
            'configuration_id' => $this->configurationId,
            'quantity' => $this->quantity,
            'currency' => $this->currency,
            'unit_price' => $this->unitPrice,
            'line_total' => $this->lineTotal,
            'breakdown' => array_map(
                static fn (PriceStageResult $stage) => $stage->toArray(),
                $this->stages,
            ),
            'volume_pricing' => $this->volumePricing,
        ];
    }
}
