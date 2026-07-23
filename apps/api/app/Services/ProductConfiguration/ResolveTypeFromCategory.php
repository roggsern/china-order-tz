<?php

namespace App\Services\ProductConfiguration;

use App\Models\Category;
use App\Models\ProductType;

/**
 * Resolves the Configuration Template (legacy ProductType) a category inherits.
 *
 * Walks parent categories until a valid active, non-deleted product_type_id is found.
 * Skips inactive/deleted templates and continues upward (Phase 1D-C).
 * Not CatalogProductType (ADR 052).
 */
class ResolveTypeFromCategory
{
    public const MAX_WALK_DEPTH = 50;

    public function handle(Category $category): ?ProductType
    {
        $current = $category;
        $seen = [];
        $depth = 0;

        while ($current !== null) {
            $depth++;
            if ($depth > self::MAX_WALK_DEPTH) {
                return null;
            }

            if (isset($seen[$current->id])) {
                return null;
            }
            $seen[$current->id] = true;

            $current->loadMissing(['productType', 'parent']);

            if ($current->product_type_id !== null) {
                $type = $this->resolveActiveTemplate($current);
                if ($type !== null) {
                    return $type;
                }
                // Inactive/deleted/missing — continue walking to parent.
            }

            $current = $current->parent;
        }

        return null;
    }

    private function resolveActiveTemplate(Category $category): ?ProductType
    {
        $type = $category->productType;

        if ($type === null) {
            // Soft-deleted templates are excluded from the relation; treat as skip.
            return null;
        }

        if (! $type->is_active) {
            return null;
        }

        return $type;
    }
}
