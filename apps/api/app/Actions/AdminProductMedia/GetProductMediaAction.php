<?php

namespace App\Actions\AdminProductMedia;

use App\Models\Product;
use App\Models\ProductMedia;
use App\Support\ProductMedia\LegacyProductMediaBridge;
use Illuminate\Database\Eloquent\Collection;

class GetProductMediaAction
{
    /**
     * @return Collection<int, ProductMedia>
     */
    public function handle(Product $product, ?string $productVariantId = null): Collection
    {
        if ($productVariantId !== null) {
            return ProductMedia::query()
                ->with('variant')
                ->where('product_id', $product->id)
                ->where('product_variant_id', $productVariantId)
                ->ordered()
                ->get();
        }

        if ($product->media()->active()->exists()) {
            return $product->media()->with('variant')->get();
        }

        $legacyBridge = LegacyProductMediaBridge::fromProductImages($product);

        if ($legacyBridge->isNotEmpty()) {
            return $legacyBridge;
        }

        return $product->media()->with('variant')->get();
    }
}
