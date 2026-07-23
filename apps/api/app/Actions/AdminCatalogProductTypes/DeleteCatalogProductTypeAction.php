<?php

namespace App\Actions\AdminCatalogProductTypes;

use App\Models\CatalogProductType;
use App\Models\Product;
use Illuminate\Http\Exceptions\HttpResponseException;

class DeleteCatalogProductTypeAction
{
    public function handle(CatalogProductType $catalogProductType): void
    {
        $productsCount = Product::query()
            ->where('catalog_product_type_id', $catalogProductType->id)
            ->count();

        if ($productsCount > 0) {
            throw new HttpResponseException(response()->json([
                'success' => false,
                'message' => sprintf(
                    'This Catalog Product Type is used by %d products. Reassign or remove those products before deleting it.',
                    $productsCount,
                ),
                'error_code' => 'catalog_product_type_in_use',
                'products_count' => $productsCount,
            ], 409));
        }

        $catalogProductType->delete();
    }
}
