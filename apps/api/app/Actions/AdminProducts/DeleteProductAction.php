<?php

namespace App\Actions\AdminProducts;

use App\Models\Product;

class DeleteProductAction
{
    public function handle(Product $product): void
    {
        $product->delete();
    }
}
