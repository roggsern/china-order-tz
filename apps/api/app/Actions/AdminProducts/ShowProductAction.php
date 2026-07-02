<?php

namespace App\Actions\AdminProducts;

use App\Models\Product;

class ShowProductAction
{
    public function handle(Product $product): Product
    {
        return $product->load(['category', 'brand', 'inventory']);
    }
}
