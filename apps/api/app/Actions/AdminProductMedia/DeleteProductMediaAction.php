<?php

namespace App\Actions\AdminProductMedia;

use App\Models\Product;
use App\Models\ProductMedia;
use App\Services\ProductMedia\ProductMediaDeleteSyncService;

class DeleteProductMediaAction
{
    public function __construct(
        private readonly ProductMediaDeleteSyncService $deleteSync,
    ) {}

    public function handle(Product $product, ProductMedia $media): void
    {
        if ($media->product_id !== $product->id) {
            abort(404);
        }

        $this->deleteSync->deleteFromCatalogMedia($media);
    }
}
