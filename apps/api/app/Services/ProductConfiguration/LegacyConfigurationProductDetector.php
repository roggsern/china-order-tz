<?php

namespace App\Services\ProductConfiguration;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Builder;

/**
 * RC1-A4 P0-3 — Detect products that still depend on the legacy Configuration Template engine.
 *
 * Legacy sellable rows are stored in product_variants but linked via the config-engine pivot
 * product_variant_attribute_value (ProductAttributeValue), not catalog_attribute_options.
 */
final class LegacyConfigurationProductDetector
{
    public function isLegacyConfigurationProduct(Product $product): bool
    {
        if (array_key_exists('legacy_configuration_product', $product->getAttributes())) {
            return (bool) $product->getAttribute('legacy_configuration_product');
        }

        if ($product->relationLoaded('variants')) {
            foreach ($product->variants as $variant) {
                if ($this->variantUsesLegacyConfigurationEngine($variant)) {
                    return true;
                }
            }

            return false;
        }

        return ProductVariant::query()
            ->where('product_id', $product->id)
            ->whereHas('attributeValues')
            ->exists();
    }

    /**
     * Efficient list/show eager-load helper — adds legacy_configuration_product exists column.
     *
     * @param  Builder<Product>  $query
     */
    public function applyExistsSelect(Builder $query): Builder
    {
        return $query->withExists([
            'variants as legacy_configuration_product' => function (Builder $variantQuery): void {
                $variantQuery->whereHas('attributeValues');
            },
        ]);
    }

    private function variantUsesLegacyConfigurationEngine(ProductVariant $variant): bool
    {
        if ($variant->relationLoaded('attributeValues')) {
            return $variant->attributeValues->isNotEmpty();
        }

        return $variant->attributeValues()->exists();
    }
}
