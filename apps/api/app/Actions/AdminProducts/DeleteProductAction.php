<?php

namespace App\Actions\AdminProducts;

use App\Models\Product;
use App\Services\AdminProducts\ProductDeletionLifecycle;

class DeleteProductAction
{
    public function __construct(
        private readonly ProductDeletionLifecycle $lifecycle,
    ) {}

    public function handle(Product $product): void
    {
        $this->lifecycle->softDelete($product);
    }
}
