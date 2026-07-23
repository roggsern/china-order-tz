<?php

namespace App\Support\Catalog;

use App\Models\Category;
use Illuminate\Validation\ValidationException;

/**
 * Catalog Product Type parent policy (ADR 052 / Phase 1D-B).
 *
 * A leaf category is an active, non-deleted category with no active,
 * non-deleted child categories. Roots without children and nested leaves
 * are both valid CPT parents.
 */
final class CatalogLeafCategoryRules
{
    /**
     * @throws ValidationException
     */
    public static function assertValidLeafParent(string $categoryId, string $field = 'subcategory_id'): void
    {
        $category = Category::query()->find($categoryId);

        if ($category === null) {
            if (Category::onlyTrashed()->whereKey($categoryId)->exists()) {
                throw ValidationException::withMessages([
                    $field => ['The selected category has been deleted.'],
                ]);
            }

            throw ValidationException::withMessages([
                $field => ['The selected category is invalid.'],
            ]);
        }

        if (! $category->is_active) {
            throw ValidationException::withMessages([
                $field => ['The selected category must be active.'],
            ]);
        }

        if (! self::isLeaf($category)) {
            throw ValidationException::withMessages([
                $field => ['Catalog Product Types must attach to a leaf category (a category with no active child categories).'],
            ]);
        }
    }

    public static function isLeaf(Category $category): bool
    {
        if (! $category->is_active) {
            return false;
        }

        return ! Category::query()
            ->where('parent_id', $category->id)
            ->where('is_active', true)
            ->exists();
    }
}
