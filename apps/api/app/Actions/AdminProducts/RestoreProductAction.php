<?php

namespace App\Actions\AdminProducts;

use App\Models\Product;

class RestoreProductAction
{
    public function handle(string $id): Product
    {
        $product = Product::onlyTrashed()->findOrFail($id);

        $product->restore();

        return $product->load(['category', 'brand', 'inventory']);
    }
}
