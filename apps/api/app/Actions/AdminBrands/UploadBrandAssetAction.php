<?php

namespace App\Actions\AdminBrands;

use App\Http\Requests\Admin\UploadBrandAssetRequest;
use App\Models\Brand;
use App\Support\Security\SecureImageUpload;
use Illuminate\Support\Facades\Storage;

class UploadBrandAssetAction
{
    public function handle(UploadBrandAssetRequest $request, Brand $brand): Brand
    {
        $field = $request->validated('field');
        $path = SecureImageUpload::storePublic($request->file('file'), 'brands');
        $url = Storage::disk('public')->url($path);

        $brand->update([
            $field => $url,
        ]);

        return $brand->fresh(['categories'])->loadCount('products');
    }
}
