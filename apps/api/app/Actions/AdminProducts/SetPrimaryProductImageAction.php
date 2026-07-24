<?php

namespace App\Actions\AdminProducts;

use App\Models\ProductImage;
use App\Services\ProductMedia\ProductPrimarySyncService;

class SetPrimaryProductImageAction
{
    public function __construct(
        private readonly ProductPrimarySyncService $primarySync,
    ) {}

    public function handle(ProductImage $image): void
    {
        $this->primarySync->setPrimaryFromLegacyImage($image);
    }
}
