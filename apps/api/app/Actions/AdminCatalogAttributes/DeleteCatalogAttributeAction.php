<?php

namespace App\Actions\AdminCatalogAttributes;

use App\Models\CatalogAttribute;

class DeleteCatalogAttributeAction
{
    public function handle(CatalogAttribute $catalogAttribute): void
    {
        $catalogAttribute->delete();
    }
}
