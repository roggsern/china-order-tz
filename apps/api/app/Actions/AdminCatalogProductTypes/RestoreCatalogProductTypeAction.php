<?php

namespace App\Actions\AdminCatalogProductTypes;

use App\Models\CatalogProductType;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class RestoreCatalogProductTypeAction
{
    public function handle(string $id): CatalogProductType
    {
        $catalogProductType = CatalogProductType::onlyTrashed()->whereKey($id)->first();

        if ($catalogProductType === null) {
            throw (new ModelNotFoundException)->setModel(CatalogProductType::class, [$id]);
        }

        $catalogProductType->restore();

        return $catalogProductType->fresh([
            'subcategory.parent.department',
            'subcategory.department',
        ]);
    }
}
