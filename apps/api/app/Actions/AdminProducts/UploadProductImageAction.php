<?php

namespace App\Actions\AdminProducts;

use App\Http\Requests\Admin\StoreProductImageRequest;
use App\Models\Product;
use App\Models\ProductImage;
use App\Support\Security\SecureImageUpload;

class UploadProductImageAction
{
    public function handle(StoreProductImageRequest $request, Product $product): ProductImage
    {
        $path = SecureImageUpload::storePublic($request->file('image'), 'products');

        return ProductImage::create([
            'product_id' => $product->id,
            'path' => $path,
            'is_primary' => false,
        ]);
    }
}
