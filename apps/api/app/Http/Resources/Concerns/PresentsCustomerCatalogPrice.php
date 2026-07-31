<?php

namespace App\Http\Resources\Concerns;

use App\Enums\PurchasabilityPath;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\Pricing\CommercePricingResolver;
use App\Services\Pricing\DTOs\CommercePricingContext;
use App\Services\ProductPurchasability\ProductPurchasabilityPolicy;

/**
 * Customer-facing catalog prices via CommercePricingResolver (ADR 054).
 * Does not invent prices — mirrors quote/cart authority including legacy fallback.
 */
trait PresentsCustomerCatalogPrice
{
    protected function commercePricingContext(): CommercePricingContext
    {
        return new CommercePricingContext(
            currency: 'TZS',
            allowLegacyVariantFallback: true,
        );
    }

    /**
     * Card/PDP product-level display price (min active variant retail, else products.price).
     */
    protected function resolvedCatalogDisplayPrice(?Product $product = null): string
    {
        $product ??= $this->resource;
        $resolver = app(CommercePricingResolver::class);
        $context = $this->commercePricingContext();
        $path = app(ProductPurchasabilityPolicy::class)->resolvePath($product);

        if ($path === PurchasabilityPath::Variant) {
            $variants = $product->relationLoaded('variants')
                ? $product->variants->filter(fn (ProductVariant $variant) => (bool) $variant->is_active)
                : $product->variants()->where('is_active', true)->with('prices')->get();

            $amounts = [];
            foreach ($variants as $variant) {
                $result = $resolver->resolveVariantProductPrice($variant, $context, $product);
                if ($result->resolved && (float) $result->unitPrice > 0) {
                    $amounts[] = (float) $result->unitPrice;
                }
            }

            if ($amounts !== []) {
                return number_format(min($amounts), 2, '.', '');
            }
        }

        return $resolver->resolveSimpleProductPrice($product, $context)->unitPrice;
    }

    /**
     * Variant/configuration line display price.
     */
    protected function resolvedVariantDisplayPrice(
        ProductVariant $variant,
        ?Product $product = null,
    ): ?string {
        $product ??= $variant->relationLoaded('product') ? $variant->product : $variant->product()->first();
        $result = app(CommercePricingResolver::class)->resolveVariantProductPrice(
            $variant,
            $this->commercePricingContext(),
            $product,
        );

        if (! $result->resolved || (float) $result->unitPrice <= 0) {
            return null;
        }

        return $result->unitPrice;
    }
}
