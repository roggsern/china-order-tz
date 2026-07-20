<?php

namespace App\Services\Pricing\Stages;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\Pricing\Contracts\PriceStageInterface;
use App\Services\Pricing\DTOs\PriceQuoteInput;
use App\Services\Pricing\DTOs\PriceStageResult;

final class BasePriceStage implements PriceStageInterface
{
    public function key(): string
    {
        return 'base';
    }

    public function label(): string
    {
        return 'Base Price';
    }

    public function apply(
        Product $product,
        ?ProductVariant $configuration,
        PriceQuoteInput $input,
        string $currentUnitPrice,
    ): PriceStageResult {
        $unitPrice = $this->format($product->price);

        return new PriceStageResult(
            stage: $this->key(),
            label: $this->label(),
            unitPrice: $unitPrice,
            applied: true,
            note: 'Product base price',
        );
    }

    private function format(mixed $amount): string
    {
        return number_format((float) $amount, 2, '.', '');
    }
}
