<?php

namespace App\Actions\AdminBrands;

use App\Models\Brand;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class RestoreBrandAction
{
    public function handle(string $id): Brand
    {
        $brand = Brand::onlyTrashed()->whereKey($id)->first();

        if ($brand === null) {
            throw (new ModelNotFoundException)->setModel(Brand::class, [$id]);
        }

        $brand->restore();

        return $brand->fresh(['categories'])->loadCount('products');
    }
}
