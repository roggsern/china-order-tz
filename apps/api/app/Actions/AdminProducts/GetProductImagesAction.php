<?php

namespace App\Actions\AdminProducts;

use App\Models\Product;
use Illuminate\Database\Eloquent\Collection;

class GetProductImagesAction
{
    /**
     * @return Collection<int, \App\Models\ProductImage>
     */
    public function handle(Product $product): Collection
    {
        return $product->images()
            ->orderByDesc('is_primary')
            ->orderBy('created_at')
            ->get();
    }
}
