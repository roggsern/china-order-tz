<?php

namespace App\Actions\AdminCatalogAttributes;

use App\Models\CatalogAttribute;

class ShowCatalogAttributeAction
{
    public function handle(CatalogAttribute $catalogAttribute): CatalogAttribute
    {
        return $catalogAttribute->load(['options', 'catalogProductTypes']);
    }
}
