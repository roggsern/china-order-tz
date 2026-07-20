<?php

namespace App\Actions\AdminBrands;

use App\Models\Brand;

class ShowBrandAction
{
    public function handle(Brand $brand): Brand
    {
        return $brand->load(['categories'])->loadCount('products');
    }
}
