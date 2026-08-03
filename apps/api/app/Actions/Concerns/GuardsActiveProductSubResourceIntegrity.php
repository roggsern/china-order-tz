<?php

namespace App\Actions\Concerns;

use App\Models\Admin;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\AdminProducts\ClearSimpleProductCommerceOnVariantPathActivation;
use App\Services\ProductPurchasability\ProductPurchasabilityPolicy;
use Illuminate\Support\Facades\Auth;

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

    protected function afterVariantPurchasabilityMutation(
        ProductPurchasabilityPolicy $policy,
        ClearSimpleProductCommerceOnVariantPathActivation $simpleCommerceCleaner,
        Product $product,
        bool $hadSellableVariantsBefore,
    ): void {
        $fresh = $product->fresh([
            'commerceChannel',
            'variants.prices',
            'variants.inventories',
            'inventory',
        ]) ?? $product;

        /** @var Admin|null $admin */
        $admin = Auth::user() instanceof Admin ? Auth::user() : null;

        $simpleCommerceCleaner->handle($fresh, $hadSellableVariantsBefore, $admin);

        $this->assertActiveProductIntegrityAfterMutation(
            $policy,
            $fresh->fresh([
                'commerceChannel',
                'variants.prices',
                'variants.inventories',
            ]) ?? $fresh,
            $hadSellableVariantsBefore,
        );
    }

    protected function productFromVariant(ProductVariant $variant): Product
    {
        $variant->loadMissing('product');

        return $variant->product;
    }
}
