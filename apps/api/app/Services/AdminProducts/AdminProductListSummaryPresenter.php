<?php

namespace App\Services\AdminProducts;

use App\Enums\PurchasabilityPath;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\Catalog\CustomerProductMediaResolver;
use App\Services\Inventory\CatalogStockPresenter;
use App\Services\Pricing\CommercePricingResolver;
use App\Services\Pricing\DTOs\CommercePricingContext;
use App\Services\ProductPurchasability\ProductPurchasabilityPolicy;

/**
 * Additive list summaries for admin product index — reuses media, pricing, and inventory engines.
 */
class AdminProductListSummaryPresenter
{
    public function __construct(
        private readonly CustomerProductMediaResolver $mediaResolver,
        private readonly CatalogStockPresenter $stockPresenter,
        private readonly ProductPurchasabilityPolicy $purchasability,
        private readonly CommercePricingResolver $pricingResolver,
    ) {}

    /**
     * @return array{id: string, path: string|null, url: string|null, alt_text: string|null}|null
     */
    public function image(Product $product): ?array
    {
        return $this->mediaResolver->resolvePrimary($product);
    }

    public function variantsCount(Product $product): int
    {
        if (isset($product->variants_count)) {
            return (int) $product->variants_count;
        }

        if ($product->relationLoaded('variants')) {
            return $product->variants->count();
        }

        return (int) $product->variants()->count();
    }

    /**
     * @return array{min: string|null, max: string|null, currency: string}
     */
    public function priceRange(Product $product): array
    {
        $currency = 'TZS';
        $amounts = [];

        if ($product->relationLoaded('variants') && $product->variants->isNotEmpty()) {
            $active = $product->variants->filter(fn (ProductVariant $variant) => (bool) $variant->is_active);
            $pool = $active->isNotEmpty() ? $active : $product->variants;

            foreach ($pool as $variant) {
                $amounts[] = (float) $this->effectivePriceAmount($variant, $product);
            }
        }

        if ($amounts === []) {
            $simple = $this->pricingResolver->resolveSimpleProductPrice(
                $product,
                new CommercePricingContext(allowLegacyVariantFallback: true),
            );
            if (! $simple->resolved || (float) $simple->unitPrice <= 0) {
                return [
                    'min' => null,
                    'max' => null,
                    'currency' => $currency,
                ];
            }

            return [
                'min' => $simple->unitPrice,
                'max' => $simple->unitPrice,
                'currency' => $currency,
            ];
        }

        $min = min($amounts);
        $max = max($amounts);

        return [
            'min' => number_format($min, 2, '.', ''),
            'max' => number_format($max, 2, '.', ''),
            'currency' => $currency,
        ];
    }

    /**
     * @return array{
     *   path: string,
     *   total_available: int,
     *   variants_in_stock: int,
     *   variants_out_of_stock: int
     * }
     */
    public function stockSummary(Product $product): array
    {
        $path = $this->purchasability->resolvePath($product);

        if ($path === PurchasabilityPath::Variant) {
            $variants = $product->relationLoaded('variants')
                ? $product->variants->filter(fn (ProductVariant $variant) => (bool) $variant->is_active)
                : $product->variants()->where('is_active', true)->get();

            $total = 0;
            $inStock = 0;
            $outOfStock = 0;

            foreach ($variants as $variant) {
                $available = $this->stockPresenter->availableForVariant($variant, $product);
                $total += $available;
                if ($available > 0) {
                    $inStock++;
                } else {
                    $outOfStock++;
                }
            }

            return [
                'path' => PurchasabilityPath::Variant->value,
                'total_available' => $total,
                'variants_in_stock' => $inStock,
                'variants_out_of_stock' => $outOfStock,
            ];
        }

        $available = $this->stockPresenter->availableForSimple($product);

        return [
            'path' => PurchasabilityPath::Simple->value,
            'total_available' => $available,
            'variants_in_stock' => 0,
            'variants_out_of_stock' => 0,
        ];
    }

    /**
     * @return array{id: string, name: string, code: string, slug: string|null}|null
     */
    public function store(Product $product): ?array
    {
        if (! $product->relationLoaded('store') || $product->store === null) {
            return null;
        }

        return [
            'id' => $product->store->id,
            'name' => (string) $product->store->name,
            'code' => (string) $product->store->code,
            'slug' => $product->store->slug,
        ];
    }

    private function effectivePriceAmount(ProductVariant $variant, Product $product): float
    {
        $result = $this->pricingResolver->resolveVariantProductPrice(
            $variant,
            new CommercePricingContext(
                currency: 'TZS',
                allowLegacyVariantFallback: true,
            ),
            $product,
        );

        if (! $result->resolved || (float) $result->unitPrice <= 0) {
            return 0.0;
        }

        return (float) $result->unitPrice;
    }
}
