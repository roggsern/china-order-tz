<?php

namespace App\Actions\AdminProductMedia;

use App\Models\Product;
use App\Models\ProductMedia;

class DeleteProductMediaAction
{
    public function handle(Product $product, ProductMedia $media): void
    {
        if ($media->product_id !== $product->id) {
            abort(404);
        }

        $media->delete();
    }
}
