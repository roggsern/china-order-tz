<?php

namespace App\Actions\AdminProducts;

use App\Http\Requests\Admin\StoreProductImageRequest;
use App\Models\Product;
use App\Services\ProductMedia\ProductImageWriteSyncResult;
use App\Services\ProductMedia\ProductImageWriteSyncService;

/**
 * Legacy /products/{id}/images upload endpoint.
 * Writes catalog product_media only (no new product_images rows).
 */
class UploadProductImageAction
{
    public function __construct(
        private readonly ProductImageWriteSyncService $imageWriteSync,
    ) {}

    public function handle(StoreProductImageRequest $request, Product $product): ProductImageWriteSyncResult
    {
        return $this->imageWriteSync->storeUploadedImage(
            $request->file('image'),
            $product,
        );
    }
}
