<?php

namespace App\Services\Pricing\Stages;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\Pricing\Contracts\PriceStageInterface;
use App\Services\Pricing\DTOs\PriceQuoteInput;
use App\Services\Pricing\DTOs\PriceStageResult;

final class ConfigurationOverrideStage implements PriceStageInterface
{
    public function key(): string
    {
        return 'configuration_override';
    }

    public function label(): string
    {
        return 'Configuration Price Override';
    }

    public function apply(
        Product $product,
        ?ProductVariant $configuration,
        PriceQuoteInput $input,
        string $currentUnitPrice,
    ): PriceStageResult {
        $product->loadMissing('productType');

        if ($configuration === null) {
            return new PriceStageResult(
                stage: $this->key(),
                label: $this->label(),
                unitPrice: $currentUnitPrice,
                applied: false,
                note: 'No configuration selected',
            );
        }

        if ($product->productType && ! $product->productType->allows_price_override) {
            return new PriceStageResult(
                stage: $this->key(),
                label: $this->label(),
                unitPrice: $currentUnitPrice,
                applied: false,
                note: 'Product type does not allow configuration price overrides',
                meta: ['configuration_id' => $configuration->id],
            );
        }

        if ($configuration->price === null) {
            return new PriceStageResult(
                stage: $this->key(),
                label: $this->label(),
                unitPrice: $currentUnitPrice,
                applied: false,
                note: 'Configuration has no price override',
                meta: ['configuration_id' => $configuration->id],
            );
        }

        $unitPrice = number_format((float) $configuration->price, 2, '.', '');

        return new PriceStageResult(
            stage: $this->key(),
            label: $this->label(),
            unitPrice: $unitPrice,
            applied: true,
            note: 'Configuration-specific price applied',
            meta: [
                'configuration_id' => $configuration->id,
                'configuration_name' => $configuration->name,
            ],
        );
    }
}
