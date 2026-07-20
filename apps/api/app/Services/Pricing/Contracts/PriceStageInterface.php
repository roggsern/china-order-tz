<?php

namespace App\Services\Pricing\Contracts;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\Pricing\DTOs\PriceQuoteInput;
use App\Services\Pricing\DTOs\PriceStageResult;

interface PriceStageInterface
{
    public function key(): string;

    public function label(): string;

    public function apply(
        Product $product,
        ?ProductVariant $configuration,
        PriceQuoteInput $input,
        string $currentUnitPrice,
    ): PriceStageResult;
}
