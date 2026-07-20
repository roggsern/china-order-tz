<?php

namespace App\Actions\AdminCatalogAttributes;

use App\Models\CatalogAttributeOption;

class DeleteCatalogAttributeOptionAction
{
    public function handle(CatalogAttributeOption $catalogAttributeOption): void
    {
        $catalogAttributeOption->delete();
    }
}
