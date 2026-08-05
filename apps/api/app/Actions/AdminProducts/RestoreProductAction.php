<?php

namespace App\Actions\AdminProducts;

use App\Models\Product;
use App\Services\AdminProducts\ProductDeletionLifecycle;

class RestoreProductAction
{
    public function __construct(
        private readonly ProductDeletionLifecycle $lifecycle,
    ) {}

    public function handle(string $id): Product
    {
        $product = Product::onlyTrashed()->findOrFail($id);

        return $this->lifecycle->restore($product);
    }
}
