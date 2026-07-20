<?php

namespace App\Actions\AdminProductMedia;

use App\Models\Product;
use Illuminate\Database\Eloquent\Collection;

class GetProductMediaAction
{
    /**
     * @return Collection<int, \App\Models\ProductMedia>
     */
    public function handle(Product $product): Collection
    {
        return $product->media()->get();
    }
}
