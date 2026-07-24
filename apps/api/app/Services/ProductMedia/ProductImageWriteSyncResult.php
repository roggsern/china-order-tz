<?php

namespace App\Services\ProductMedia;

use App\Models\ProductImage;
use App\Models\ProductMedia;

final class ProductImageWriteSyncResult
{
    public function __construct(
        public readonly ProductImage $legacyImage,
        public readonly ProductMedia $catalogMedia,
    ) {}
}
