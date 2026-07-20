<?php

namespace App\Actions\AdminCatalogAttributes;

use App\Http\Requests\Admin\SyncCatalogProductTypeAttributesRequest;
use App\Models\CatalogProductType;

class SyncCatalogProductTypeAttributesAction
{
    public function handle(
        SyncCatalogProductTypeAttributesRequest $request,
        CatalogProductType $catalogProductType,
    ): CatalogProductType {
        $validated = $request->validated();
        $sync = [];

        foreach (array_values($validated['attributes']) as $index => $row) {
            $sync[$row['catalog_attribute_id']] = [
                'is_required' => $row['is_required'] ?? false,
                'sort_order' => $row['sort_order'] ?? ($index + 1),
            ];
        }

        $catalogProductType->attributes()->sync($sync);

        return $catalogProductType->fresh([
            'attributes.options',
        ]);
    }
}
