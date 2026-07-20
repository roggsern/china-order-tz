<?php

namespace App\Actions\AdminProductMedia;

use App\Models\Product;
use App\Models\ProductMedia;
use Illuminate\Validation\ValidationException;

class DeleteProductMediaAction
{
    public function handle(Product $product, ProductMedia $media): void
    {
        if ($media->product_id !== $product->id) {
            throw ValidationException::withMessages([
                'media' => ['Media does not belong to this product.'],
            ]);
        }

        $media->delete();
    }
}
