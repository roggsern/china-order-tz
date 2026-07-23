<?php

namespace App\Services\Pricing\Stages;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\Pricing\CommercePricingResolver;
use App\Services\Pricing\Contracts\PriceStageInterface;
use App\Services\Pricing\DTOs\PriceQuoteInput;
use App\Services\Pricing\DTOs\PriceStageResult;

final class BasePriceStage implements PriceStageInterface
{
    public function __construct(
        private readonly CommercePricingResolver $commercePricingResolver,
    ) {}

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
        // Quote pipeline base remains products.price via CommercePricingResolver (ADR 054 Simple SSoT).
        $resolved = $this->commercePricingResolver->resolveSimpleProductPrice($product);
        $unitPrice = $resolved->unitPrice;

        return new PriceStageResult(
            stage: $this->key(),
            label: $this->label(),
            unitPrice: $unitPrice,
            applied: true,
            note: 'Product base price',
            meta: [
                'source' => $resolved->source,
            ],
        );
    }
}
