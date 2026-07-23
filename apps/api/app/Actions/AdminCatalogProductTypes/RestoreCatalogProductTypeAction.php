<?php

namespace App\Actions\AdminCatalogProductTypes;

use App\Models\CatalogProductType;
use App\Support\Catalog\CatalogLeafCategoryRules;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\ValidationException;

class RestoreCatalogProductTypeAction
{
    public function handle(string $id): CatalogProductType
    {
        $catalogProductType = CatalogProductType::onlyTrashed()->whereKey($id)->first();

        if ($catalogProductType === null) {
            throw (new ModelNotFoundException)->setModel(CatalogProductType::class, [$id]);
        }

        try {
            CatalogLeafCategoryRules::assertValidLeafParent((string) $catalogProductType->subcategory_id);
        } catch (ValidationException $e) {
            $first = collect($e->errors())->flatten()->first()
                ?: 'Cannot restore this Catalog Product Type because its parent category is not a valid leaf.';

            throw new HttpResponseException(response()->json([
                'success' => false,
                'message' => $first,
                'error_code' => 'catalog_product_type_restore_parent_invalid',
                'errors' => $e->errors(),
            ], 422));
        }

        $catalogProductType->restore();

        return $catalogProductType->fresh([
            'subcategory.parent.department',
            'subcategory.department',
        ])->loadCount(['products', 'attributes']);
    }
}
