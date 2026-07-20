<?php

namespace App\Services\ProductConfiguration;

use App\Models\Category;
use App\Models\ProductType;

/**
 * Resolves the Product Type a category inherits.
 * Walks parent categories until a product_type_id is found.
 * No product-specific hardcoding — inheritance is metadata on categories.
 */
class ResolveTypeFromCategory
{
    public function handle(Category $category): ?ProductType
    {
        $current = $category;

        while ($current !== null) {
            $current->loadMissing(['productType', 'parent']);

            if ($current->product_type_id !== null) {
                return $current->productType
                    ?? ProductType::query()->find($current->product_type_id);
            }

            $current = $current->parent;
        }

        return null;
    }
}
