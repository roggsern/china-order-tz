<?php

namespace App\Actions\AdminBrands;

use App\Http\Requests\Admin\SyncBrandCategoriesRequest;
use App\Models\Brand;

class SyncBrandCategoriesAction
{
    public function handle(SyncBrandCategoriesRequest $request, Brand $brand): Brand
    {
        $brand->categories()->sync($request->validated('category_ids') ?? []);

        return $brand->fresh(['categories'])->loadCount('products');
    }
}
