<?php

namespace App\Actions\AdminProducts;

use App\Models\Product;

class ForceDeleteProductAction
{
    public function handle(string $id): void
    {
        $product = Product::onlyTrashed()->findOrFail($id);

        $product->forceDelete();
    }
}
