<?php

namespace App\Actions\AdminCatalogAttributes;

use App\Models\CatalogAttribute;
use App\Models\CatalogProductType;
use Illuminate\Support\Collection;

class GetCatalogFiltersAction
{
    /**
     * @return Collection<int, CatalogAttribute>
     */
    public function handle(?string $catalogProductTypeId = null): Collection
    {
        $query = CatalogAttribute::query()
            ->filterable()
            ->with(['options'])
            ->orderBy('sort_order')
            ->orderBy('name');

        if (is_string($catalogProductTypeId) && $catalogProductTypeId !== '') {
            $type = CatalogProductType::query()->find($catalogProductTypeId);
            if ($type === null) {
                return collect();
            }

            return $type->attributes()
                ->where('catalog_attributes.is_active', true)
                ->where('catalog_attributes.is_filterable', true)
                ->with(['options'])
                ->orderByPivot('sort_order')
                ->get();
        }

        return $query->get();
    }
}
