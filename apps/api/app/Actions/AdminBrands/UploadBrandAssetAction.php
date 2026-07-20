<?php

namespace App\Actions\AdminBrands;

use App\Http\Requests\Admin\UploadBrandAssetRequest;
use App\Models\Brand;
use Illuminate\Support\Facades\Storage;

class UploadBrandAssetAction
{
    public function handle(UploadBrandAssetRequest $request, Brand $brand): Brand
    {
        $field = $request->validated('field');
        $path = Storage::disk('public')->putFile('brands', $request->file('file'));
        $url = Storage::disk('public')->url($path);

        $brand->update([
            $field => $url,
        ]);

        return $brand->fresh(['categories'])->loadCount('products');
    }
}
