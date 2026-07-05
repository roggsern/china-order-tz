<?php

namespace App\Actions\AdminBrands;

use App\Models\Brand;

class DeleteBrandAction
{
    public function handle(Brand $brand): void
    {
        $brand->delete();
    }
}
