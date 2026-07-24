<?php

namespace App\Actions\Concerns;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\ProductPurchasability\ProductPurchasabilityPolicy;

trait GuardsActiveProductSubResourceIntegrity
{
    protected function snapshotSellableVariants(
        ProductPurchasabilityPolicy $policy,
        Product $product,
    ): bool {
        return $policy->snapshotHadSellableVariants($product);
    }

    protected function assertActiveProductIntegrityAfterMutation(
        ProductPurchasabilityPolicy $policy,
        Product $product,
        bool $hadSellableVariantsBefore,
    ): void {
        $policy->assertActiveVariantIntegrityAfterMutation(
            $product->fresh([
                'commerceChannel',
                'variants.prices',
                'variants.inventories',
            ]) ?? $product,
            $hadSellableVariantsBefore,
        );
    }

    protected function productFromVariant(ProductVariant $variant): Product
    {
        $variant->loadMissing('product');

        return $variant->product;
    }
}
