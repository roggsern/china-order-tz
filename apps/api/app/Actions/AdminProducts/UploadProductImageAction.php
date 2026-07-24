<?php

namespace App\Actions\AdminProducts;

use App\Http\Requests\Admin\StoreProductImageRequest;
use App\Models\Product;
use App\Models\ProductImage;
use App\Services\ProductMedia\ProductImageWriteSyncService;

class UploadProductImageAction
{
    public function __construct(
        private readonly ProductImageWriteSyncService $imageWriteSync,
    ) {}

    public function handle(StoreProductImageRequest $request, Product $product): ProductImage
    {
        return $this->imageWriteSync->storeUploadedImage(
            $request->file('image'),
            $product,
        )->legacyImage;
    }
}
