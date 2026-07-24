<?php

namespace App\Actions\AdminProductMedia;

use App\Models\Product;
use App\Support\ProductMedia\LegacyProductMediaBridge;
use Illuminate\Database\Eloquent\Collection;

class GetProductMediaAction
{
    /**
     * @return Collection<int, \App\Models\ProductMedia>
     */
    public function handle(Product $product): Collection
    {
        if ($product->media()->active()->exists()) {
            return $product->media()->get();
        }

        $legacyBridge = LegacyProductMediaBridge::fromProductImages($product);

        if ($legacyBridge->isNotEmpty()) {
            return $legacyBridge;
        }

        return $product->media()->get();
    }
}
