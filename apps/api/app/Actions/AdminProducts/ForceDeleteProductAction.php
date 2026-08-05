<?php

namespace App\Actions\AdminProducts;

use App\Models\Product;
use App\Services\AdminProducts\ProductDeletionLifecycle;

class ForceDeleteProductAction
{
    public function __construct(
        private readonly ProductDeletionLifecycle $lifecycle,
    ) {}

    public function handle(string $id): void
    {
        $product = Product::onlyTrashed()->findOrFail($id);

        $this->lifecycle->forceDelete($product);
    }
}
