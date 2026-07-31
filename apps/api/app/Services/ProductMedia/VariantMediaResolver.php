<?php

namespace App\Services\ProductMedia;

use App\Models\Product;
use App\Models\ProductMedia;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Collection;

/**
 * Resolves gallery media for a variant — variant-bound rows first, then product-level fallback.
 * Used by admin foundation and storefront CustomerProductMediaResolver.
 */
class VariantMediaResolver
{
    /**
     * @return Collection<int, ProductMedia>
     */
    public function resolve(Product $product, ?ProductVariant $variant = null): Collection
    {
        if ($variant !== null) {
            if ($variant->product_id !== $product->id) {
                return $this->productLevelMedia($product);
            }

            $variantMedia = $this->variantMedia($product, $variant);
            if ($variantMedia->isNotEmpty()) {
                return $variantMedia;
            }
        }

        return $this->productLevelMedia($product);
    }

    /**
     * @return Collection<int, ProductMedia>
     */
    public function resolveForVariant(ProductVariant $variant): Collection
    {
        $product = $variant->relationLoaded('product')
            ? $variant->product
            : $variant->product()->first();

        if ($product === null) {
            return new Collection;
        }

        return $this->resolve($product, $variant);
    }

    /**
     * @return Collection<int, ProductMedia>
     */
    private function variantMedia(Product $product, ProductVariant $variant): Collection
    {
        if ($variant->relationLoaded('media')) {
            return $variant->media
                ->sortBy([
                    fn (ProductMedia $media) => $media->is_primary ? 0 : 1,
                    fn (ProductMedia $media) => $media->sort_order,
                    fn (ProductMedia $media) => $media->created_at?->getTimestamp() ?? 0,
                ])
                ->values();
        }

        return ProductMedia::query()
            ->where('product_id', $product->id)
            ->where('product_variant_id', $variant->id)
            ->ordered()
            ->get();
    }

    /**
     * @return Collection<int, ProductMedia>
     */
    private function productLevelMedia(Product $product): Collection
    {
        if ($product->relationLoaded('media')) {
            return $product->media
                ->sortBy([
                    fn (ProductMedia $media) => $media->is_primary ? 0 : 1,
                    fn (ProductMedia $media) => $media->sort_order,
                    fn (ProductMedia $media) => $media->created_at?->getTimestamp() ?? 0,
                ])
                ->values();
        }

        return $product->media()->ordered()->get();
    }
}
