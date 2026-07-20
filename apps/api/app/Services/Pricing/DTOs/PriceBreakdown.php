<?php

namespace App\Services\Pricing\DTOs;

final class PriceBreakdown
{
    /**
     * @param  list<PriceStageResult>  $stages
     */
    public function __construct(
        public readonly string $productId,
        public readonly ?string $configurationId,
        public readonly int $quantity,
        public readonly string $currency,
        public readonly string $unitPrice,
        public readonly string $lineTotal,
        public readonly array $stages,
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
        ];
    }
}
