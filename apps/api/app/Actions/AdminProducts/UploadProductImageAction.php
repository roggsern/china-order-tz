<?php

namespace App\Actions\AdminProducts;

use App\Http\Requests\Admin\StoreProductImageRequest;
use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Support\Facades\Storage;

class UploadProductImageAction
{
    public function handle(StoreProductImageRequest $request, Product $product): ProductImage
    {
        $path = Storage::disk('public')->putFile('products', $request->file('image'));

        return ProductImage::create([
            'product_id' => $product->id,
            'path' => $path,
            'is_primary' => false,
        ]);
    }
}
