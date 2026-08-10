<?php

namespace App\Services\Search;

use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;

/**
 * Shared LIKE match + CASE relevance ranking for marketplace search.
 * No external search engine — SQL + in-PHP score/matched_on only.
 *
 * Taxonomy: Category (parent) → Subcategory (Category.parent_id) → CatalogProductType → Product.
 * Products typically attach to the leaf subcategory via category_id.
 */
class SearchRelevance
{
    public const SCORE_EXACT_NAME = 600;

    public const SCORE_NAME_CONTAINS = 500;

    public const SCORE_BRAND = 450;

    public const SCORE_STORE = 400;

    public const SCORE_SKU = 300;

    /** Parent / root category name match. */
    public const SCORE_CATEGORY = 200;

    /** Leaf subcategory (or catalog product type) name match. */
    public const SCORE_SUBCATEGORY = 180;

    /** @deprecated Prefer SCORE_CATEGORY / SCORE_SUBCATEGORY */
    public const SCORE_CATEGORY_OR_TYPE = 200;

    public const SCORE_SHORT_DESCRIPTION = 100;

    public const SCORE_DESCRIPTION = 50;

    /**
     * @param  array{include_store?: bool}  $options
     */
    public function applyProductMatchFilter(Builder $query, string $search, array $options = []): void
    {
        $term = '%'.$this->normalize($search).'%';
        $includeStore = (bool) ($options['include_store'] ?? false);

        $query->where(function (Builder $q) use ($term, $includeStore) {
            $q->whereRaw('LOWER(products.name) LIKE ?', [$term])
                ->orWhereRaw('LOWER(COALESCE(products.short_description, \'\')) LIKE ?', [$term])
                ->orWhereRaw('LOWER(COALESCE(products.description, \'\')) LIKE ?', [$term])
                ->orWhereRaw('LOWER(COALESCE(products.sku, \'\')) LIKE ?', [$term])
                ->orWhereHas(
                    'brand',
                    fn (Builder $brandQuery) => $brandQuery->whereRaw('LOWER(name) LIKE ?', [$term]),
                )
                ->orWhereHas(
                    'catalogProductType',
                    fn (Builder $typeQuery) => $typeQuery->whereRaw('LOWER(name) LIKE ?', [$term]),
                )
                // Direct category / subcategory attached to the product.
                ->orWhereHas(
                    'category',
                    fn (Builder $categoryQuery) => $this->applyCategoryNameOrSlugMatch($categoryQuery, $term),
                )
                // Parent category when product.category_id is a subcategory leaf.
                ->orWhereHas(
                    'category.parent',
                    fn (Builder $parentQuery) => $this->applyCategoryNameOrSlugMatch($parentQuery, $term),
                )
                // Taxonomy via catalog product type → subcategory → parent category.
                ->orWhereHas(
                    'catalogProductType.subcategory',
                    fn (Builder $subQuery) => $this->applyCategoryNameOrSlugMatch($subQuery, $term),
                )
                ->orWhereHas(
                    'catalogProductType.subcategory.parent',
                    fn (Builder $parentQuery) => $this->applyCategoryNameOrSlugMatch($parentQuery, $term),
                )
                // Optional many-to-many category attachments.
                ->orWhereHas(
                    'categories',
                    fn (Builder $categoryQuery) => $this->applyCategoryNameOrSlugMatch($categoryQuery, $term),
                )
                ->orWhereHas(
                    'categories.parent',
                    fn (Builder $parentQuery) => $this->applyCategoryNameOrSlugMatch($parentQuery, $term),
                );

            if ($includeStore) {
                $q->orWhereHas('store', function (Builder $storeQuery) use ($term) {
                    $storeQuery->where(function (Builder $inner) use ($term) {
                        $inner->whereRaw('LOWER(name) LIKE ?', [$term])
                            ->orWhereRaw('LOWER(slug) LIKE ?', [$term]);
                    });
                });
            }
        });
    }

    /**
     * @param  array{include_store?: bool}  $options
     */
    public function applyProductRelevanceOrder(Builder $query, string $search, array $options = []): void
    {
        $normalized = $this->normalize($search);
        $like = '%'.$normalized.'%';
        $includeStore = (bool) ($options['include_store'] ?? false);

        $categoryExists = $this->sqlCategoryMatchExists();
        $subcategoryExists = $this->sqlSubcategoryMatchExists();

        if ($includeStore) {
            $query->orderByRaw(
                'CASE
                    WHEN LOWER(products.name) = ? THEN '.self::SCORE_EXACT_NAME.'
                    WHEN LOWER(products.name) LIKE ? THEN '.self::SCORE_NAME_CONTAINS.'
                    WHEN EXISTS (
                        SELECT 1 FROM brands
                        WHERE brands.id = products.brand_id
                          AND brands.deleted_at IS NULL
                          AND LOWER(brands.name) LIKE ?
                    ) THEN '.self::SCORE_BRAND.'
                    WHEN EXISTS (
                        SELECT 1 FROM stores
                        WHERE stores.id = products.store_id
                          AND stores.deleted_at IS NULL
                          AND (LOWER(stores.name) LIKE ? OR LOWER(stores.slug) LIKE ?)
                    ) THEN '.self::SCORE_STORE.'
                    WHEN LOWER(COALESCE(products.sku, \'\')) LIKE ? THEN '.self::SCORE_SKU.'
                    WHEN '.$categoryExists.' THEN '.self::SCORE_CATEGORY.'
                    WHEN '.$subcategoryExists.' THEN '.self::SCORE_SUBCATEGORY.'
                    WHEN LOWER(COALESCE(products.short_description, \'\')) LIKE ? THEN '.self::SCORE_SHORT_DESCRIPTION.'
                    WHEN LOWER(COALESCE(products.description, \'\')) LIKE ? THEN '.self::SCORE_DESCRIPTION.'
                    ELSE 0
                END DESC',
                array_merge(
                    [$normalized, $like, $like, $like, $like, $like],
                    $this->categoryMatchBindings($like),
                    $this->subcategoryMatchBindings($like),
                    [$like, $like],
                ),
            );
        } else {
            $query->orderByRaw(
                'CASE
                    WHEN LOWER(products.name) = ? THEN '.self::SCORE_EXACT_NAME.'
                    WHEN LOWER(products.name) LIKE ? THEN '.self::SCORE_NAME_CONTAINS.'
                    WHEN EXISTS (
                        SELECT 1 FROM brands
                        WHERE brands.id = products.brand_id
                          AND brands.deleted_at IS NULL
                          AND LOWER(brands.name) LIKE ?
                    ) THEN '.self::SCORE_BRAND.'
                    WHEN LOWER(COALESCE(products.sku, \'\')) LIKE ? THEN '.self::SCORE_SKU.'
                    WHEN '.$categoryExists.' THEN '.self::SCORE_CATEGORY.'
                    WHEN '.$subcategoryExists.' THEN '.self::SCORE_SUBCATEGORY.'
                    WHEN LOWER(COALESCE(products.short_description, \'\')) LIKE ? THEN '.self::SCORE_SHORT_DESCRIPTION.'
                    WHEN LOWER(COALESCE(products.description, \'\')) LIKE ? THEN '.self::SCORE_DESCRIPTION.'
                    ELSE 0
                END DESC',
                array_merge(
                    [$normalized, $like, $like, $like],
                    $this->categoryMatchBindings($like),
                    $this->subcategoryMatchBindings($like),
                    [$like, $like],
                ),
            );
        }

        $query->latest('products.created_at');
    }

    /**
     * @return list<string>
     */
    public function matchedOn(Product $product, string $search): array
    {
        $normalized = $this->normalize($search);
        if ($normalized === '') {
            return [];
        }

        $like = static fn (?string $value): bool => $value !== null
            && $value !== ''
            && str_contains(mb_strtolower($value), $normalized);

        $matched = [];

        if ($like($product->name)) {
            $matched[] = 'name';
        }
        if ($product->relationLoaded('brand') && $product->brand && $like($product->brand->name)) {
            $matched[] = 'brand';
        }
        if ($product->relationLoaded('store') && $product->store) {
            if ($like($product->store->name) || $like($product->store->slug)) {
                $matched[] = 'store';
            }
        }
        if ($like($product->sku)) {
            $matched[] = 'sku';
        }

        $category = $product->relationLoaded('category') ? $product->category : null;
        if ($category) {
            $category->loadMissing('parent');
            $isSubcategory = $category->parent_id !== null;
            if ($like($category->name) || $like($category->slug)) {
                $matched[] = $isSubcategory ? 'subcategory' : 'category';
            }
            if ($category->parent && ($like($category->parent->name) || $like($category->parent->slug))) {
                $matched[] = 'category';
            }
        }

        if ($product->relationLoaded('catalogProductType') && $product->catalogProductType) {
            $type = $product->catalogProductType;
            $type->loadMissing('subcategory.parent');
            if ($like($type->name)) {
                $matched[] = 'type';
            }
            if ($type->subcategory) {
                if ($like($type->subcategory->name) || $like($type->subcategory->slug)) {
                    $matched[] = 'subcategory';
                }
                if ($type->subcategory->parent
                    && ($like($type->subcategory->parent->name) || $like($type->subcategory->parent->slug))) {
                    $matched[] = 'category';
                }
            }
        }

        if ($like($product->short_description)) {
            $matched[] = 'short_description';
        }
        if ($like($product->description)) {
            $matched[] = 'description';
        }

        return array_values(array_unique($matched));
    }

    public function score(Product $product, string $search): int
    {
        $normalized = $this->normalize($search);
        if ($normalized === '') {
            return 0;
        }

        $name = mb_strtolower((string) $product->name);
        if ($name === $normalized) {
            return self::SCORE_EXACT_NAME;
        }
        if (str_contains($name, $normalized)) {
            return self::SCORE_NAME_CONTAINS;
        }

        $matched = $this->matchedOn($product, $search);
        if (in_array('brand', $matched, true)) {
            return self::SCORE_BRAND;
        }
        if (in_array('store', $matched, true)) {
            return self::SCORE_STORE;
        }
        if (in_array('sku', $matched, true)) {
            return self::SCORE_SKU;
        }
        if (in_array('category', $matched, true)) {
            return self::SCORE_CATEGORY;
        }
        if (in_array('subcategory', $matched, true) || in_array('type', $matched, true)) {
            return self::SCORE_SUBCATEGORY;
        }
        if (in_array('short_description', $matched, true)) {
            return self::SCORE_SHORT_DESCRIPTION;
        }
        if (in_array('description', $matched, true)) {
            return self::SCORE_DESCRIPTION;
        }

        return 0;
    }

    public function normalize(string $search): string
    {
        return mb_strtolower(trim($search));
    }

    public function entityScore(string $name, string $slug, string $search): int
    {
        $normalized = $this->normalize($search);
        if ($normalized === '') {
            return 0;
        }

        $nameLower = mb_strtolower($name);
        $slugLower = mb_strtolower($slug);

        if ($nameLower === $normalized || $slugLower === $normalized) {
            return self::SCORE_EXACT_NAME;
        }
        if (str_starts_with($nameLower, $normalized) || str_starts_with($slugLower, $normalized)) {
            return self::SCORE_NAME_CONTAINS;
        }
        if (str_contains($nameLower, $normalized) || str_contains($slugLower, $normalized)) {
            return self::SCORE_BRAND;
        }

        return 0;
    }

    /**
     * @param  Builder<\App\Models\Category>  $query
     */
    private function applyCategoryNameOrSlugMatch(Builder $query, string $term): void
    {
        $query->where(function (Builder $inner) use ($term) {
            $inner->whereRaw('LOWER(name) LIKE ?', [$term])
                ->orWhereRaw('LOWER(slug) LIKE ?', [$term]);
        });
    }

    /**
     * Parent / root category match — 6 LIKE placeholders.
     */
    private function sqlCategoryMatchExists(): string
    {
        return '(
            EXISTS (
                SELECT 1 FROM categories
                WHERE categories.id = products.category_id
                  AND categories.deleted_at IS NULL
                  AND categories.parent_id IS NULL
                  AND (LOWER(categories.name) LIKE ? OR LOWER(categories.slug) LIKE ?)
            )
            OR EXISTS (
                SELECT 1 FROM categories AS leaf
                INNER JOIN categories AS parent
                    ON parent.id = leaf.parent_id
                   AND parent.deleted_at IS NULL
                WHERE leaf.id = products.category_id
                  AND leaf.deleted_at IS NULL
                  AND (LOWER(parent.name) LIKE ? OR LOWER(parent.slug) LIKE ?)
            )
            OR EXISTS (
                SELECT 1 FROM catalog_product_types
                INNER JOIN categories AS leaf
                    ON leaf.id = catalog_product_types.subcategory_id
                   AND leaf.deleted_at IS NULL
                INNER JOIN categories AS parent
                    ON parent.id = leaf.parent_id
                   AND parent.deleted_at IS NULL
                WHERE catalog_product_types.id = products.catalog_product_type_id
                  AND catalog_product_types.deleted_at IS NULL
                  AND (LOWER(parent.name) LIKE ? OR LOWER(parent.slug) LIKE ?)
            )
        )';
    }

    /**
     * @return list<string>
     */
    private function categoryMatchBindings(string $like): array
    {
        return [$like, $like, $like, $like, $like, $like];
    }

    /**
     * Leaf subcategory / catalog type match — 5 LIKE placeholders.
     */
    private function sqlSubcategoryMatchExists(): string
    {
        return '(
            EXISTS (
                SELECT 1 FROM categories
                WHERE categories.id = products.category_id
                  AND categories.deleted_at IS NULL
                  AND categories.parent_id IS NOT NULL
                  AND (LOWER(categories.name) LIKE ? OR LOWER(categories.slug) LIKE ?)
            )
            OR EXISTS (
                SELECT 1 FROM catalog_product_types
                INNER JOIN categories AS leaf
                    ON leaf.id = catalog_product_types.subcategory_id
                   AND leaf.deleted_at IS NULL
                WHERE catalog_product_types.id = products.catalog_product_type_id
                  AND catalog_product_types.deleted_at IS NULL
                  AND (LOWER(leaf.name) LIKE ? OR LOWER(leaf.slug) LIKE ?)
            )
            OR EXISTS (
                SELECT 1 FROM catalog_product_types
                WHERE catalog_product_types.id = products.catalog_product_type_id
                  AND catalog_product_types.deleted_at IS NULL
                  AND LOWER(catalog_product_types.name) LIKE ?
            )
        )';
    }

    /**
     * @return list<string>
     */
    private function subcategoryMatchBindings(string $like): array
    {
        return [$like, $like, $like, $like, $like];
    }
}
