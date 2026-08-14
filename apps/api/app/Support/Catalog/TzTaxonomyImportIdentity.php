<?php

namespace App\Support\Catalog;

use Illuminate\Support\Str;

/**
 * Deterministic store-scoped identity for taxonomy TEMPLATE/COPY import.
 *
 * Categories: {store-slug}-{source-category-slug}
 * Product types: {target-category-slug}-{product-type-name-slug}
 *   (matches TzCatalogProductTypeSeeder conventions)
 */
final class TzTaxonomyImportIdentity
{
    public static function categorySlug(string $storeSlug, string $sourceCategorySlug): string
    {
        $storePart = Str::slug($storeSlug);
        $sourcePart = Str::slug($sourceCategorySlug);

        if ($storePart === '') {
            $storePart = 'store';
        }
        if ($sourcePart === '') {
            $sourcePart = 'category';
        }

        return $storePart.'-'.$sourcePart;
    }

    public static function productTypeSlug(string $targetCategorySlug, string $productTypeName): string
    {
        $typePart = Str::slug($productTypeName);
        if ($typePart === '') {
            $typePart = 'product-type';
        }

        $categoryPart = trim($targetCategorySlug);
        if ($categoryPart === '') {
            return $typePart;
        }

        // Match TzCatalogProductTypeSeeder: "{category.slug}-{Str::slug(name)}"
        return $categoryPart.'-'.$typePart;
    }
}
