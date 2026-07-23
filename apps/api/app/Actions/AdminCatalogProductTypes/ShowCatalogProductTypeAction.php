<?php

namespace App\Actions\AdminCatalogProductTypes;

use App\Models\CatalogProductType;

class ShowCatalogProductTypeAction
{
    public function handle(CatalogProductType $catalogProductType): CatalogProductType
    {
        return $catalogProductType->loadMissing([
            'subcategory.parent.department',
            'subcategory.department',
        ])->loadCount(['products', 'attributes']);
    }
}
