<?php

namespace App\Actions\AdminCatalogAttributes;

use App\Models\CatalogAttribute;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class RestoreCatalogAttributeAction
{
    public function handle(string $id): CatalogAttribute
    {
        $attribute = CatalogAttribute::onlyTrashed()->whereKey($id)->first();

        if ($attribute === null) {
            throw (new ModelNotFoundException)->setModel(CatalogAttribute::class, [$id]);
        }

        $attribute->restore();

        return $attribute->fresh(['options']);
    }
}
