<?php

namespace App\Actions\AdminProducts;

use App\Models\ProductImage;
use App\Services\ProductMedia\ProductMediaDeleteSyncService;

class DeleteProductImageAction
{
    public function __construct(
        private readonly ProductMediaDeleteSyncService $deleteSync,
    ) {}

    public function handle(ProductImage $image): void
    {
        $this->deleteSync->deleteFromLegacyImage($image);
    }
}
