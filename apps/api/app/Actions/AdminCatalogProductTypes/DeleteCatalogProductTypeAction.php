<?php

namespace App\Actions\AdminCatalogProductTypes;

use App\Models\CatalogProductType;

class DeleteCatalogProductTypeAction
{
    public function handle(CatalogProductType $catalogProductType): void
    {
        $catalogProductType->delete();
    }
}
