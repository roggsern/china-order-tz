<?php

namespace App\Services\Pricing\Stages;

use App\Models\ConfigurationPriceTier;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\Pricing\Contracts\PriceStageInterface;
use App\Services\Pricing\DTOs\PriceQuoteInput;
use App\Services\Pricing\DTOs\PriceStageResult;
use App\Enums\PriceTierType;

final class QuantityTierStage implements PriceStageInterface
{
    public function key(): string
    {
        return 'quantity_tier';
    }

    public function label(): string
    {
        return 'MOQ / Quantity Tier';
    }

    public function apply(
        Product $product,
        ?ProductVariant $configuration,
        PriceQuoteInput $input,
        string $currentUnitPrice,
    ): PriceStageResult {
        $product->loadMissing('productType');

        if ($product->productType && ! $product->productType->allows_moq_pricing) {
            return new PriceStageResult(
                stage: $this->key(),
                label: $this->label(),
                unitPrice: $currentUnitPrice,
                applied: false,
                note: 'Product type does not allow MOQ / quantity tier pricing',
            );
        }

        $tier = $this->resolveTier($product, $configuration, $input->quantity);

        if ($tier === null) {
            return new PriceStageResult(
                stage: $this->key(),
                label: $this->label(),
                unitPrice: $currentUnitPrice,
                applied: false,
                note: 'No quantity tier matched for quantity '.$input->quantity,
                meta: ['quantity' => $input->quantity],
            );
        }

        $unitPrice = $tier->resolveUnitPrice($currentUnitPrice);
        $tierType = $tier->tier_type ?? PriceTierType::FixedUnit;

        return new PriceStageResult(
            stage: $this->key(),
            label: $this->label(),
            unitPrice: $unitPrice,
            applied: true,
            note: $tierType === PriceTierType::PercentOff
                ? 'Quantity tier applied (percent_off='.$tier->discount_percent.'%, min_quantity='.$tier->min_quantity.')'
                : 'Quantity tier applied (min_quantity='.$tier->min_quantity.')',
            meta: [
                'tier_id' => $tier->id,
                'min_quantity' => $tier->min_quantity,
                'quantity' => $input->quantity,
                'scope' => $tier->product_variant_id ? 'configuration' : 'product',
                'tier_type' => $tierType->value,
                'discount_percent' => $tier->discount_percent,
            ],
        );
    }

    private function resolveTier(
        Product $product,
        ?ProductVariant $configuration,
        int $quantity,
    ): ?ConfigurationPriceTier {
        if ($configuration !== null) {
            $configTier = ConfigurationPriceTier::query()
                ->where('product_variant_id', $configuration->id)
                ->where('min_quantity', '<=', $quantity)
                ->orderByDesc('min_quantity')
                ->first();

            if ($configTier !== null) {
                return $configTier;
            }
        }

        return ConfigurationPriceTier::query()
            ->where('product_id', $product->id)
            ->whereNull('product_variant_id')
            ->where('min_quantity', '<=', $quantity)
            ->orderByDesc('min_quantity')
            ->first();
    }
}
