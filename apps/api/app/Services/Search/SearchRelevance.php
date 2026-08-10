<?php

namespace App\Services\Search;

use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;

/**
 * Shared LIKE match + CASE relevance ranking for marketplace search.
 * No external search engine — SQL + in-PHP score/matched_on only.
 */
class SearchRelevance
{
    public const SCORE_EXACT_NAME = 600;

    public const SCORE_NAME_CONTAINS = 500;

    public const SCORE_BRAND = 450;

    public const SCORE_STORE = 400;

    public const SCORE_SKU = 300;

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
                ->orWhereHas(
                    'category',
                    fn (Builder $categoryQuery) => $categoryQuery->whereRaw('LOWER(name) LIKE ?', [$term]),
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
                    WHEN EXISTS (
                        SELECT 1 FROM catalog_product_types
                        WHERE catalog_product_types.id = products.catalog_product_type_id
                          AND catalog_product_types.deleted_at IS NULL
                          AND LOWER(catalog_product_types.name) LIKE ?
                    ) OR EXISTS (
                        SELECT 1 FROM categories
                        WHERE categories.id = products.category_id
                          AND categories.deleted_at IS NULL
                          AND LOWER(categories.name) LIKE ?
                    ) THEN '.self::SCORE_CATEGORY_OR_TYPE.'
                    WHEN LOWER(COALESCE(products.short_description, \'\')) LIKE ? THEN '.self::SCORE_SHORT_DESCRIPTION.'
                    WHEN LOWER(COALESCE(products.description, \'\')) LIKE ? THEN '.self::SCORE_DESCRIPTION.'
                    ELSE 0
                END DESC',
                [$normalized, $like, $like, $like, $like, $like, $like, $like, $like, $like],
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
                    WHEN EXISTS (
                        SELECT 1 FROM catalog_product_types
                        WHERE catalog_product_types.id = products.catalog_product_type_id
                          AND catalog_product_types.deleted_at IS NULL
                          AND LOWER(catalog_product_types.name) LIKE ?
                    ) OR EXISTS (
                        SELECT 1 FROM categories
                        WHERE categories.id = products.category_id
                          AND categories.deleted_at IS NULL
                          AND LOWER(categories.name) LIKE ?
                    ) THEN '.self::SCORE_CATEGORY_OR_TYPE.'
                    WHEN LOWER(COALESCE(products.short_description, \'\')) LIKE ? THEN '.self::SCORE_SHORT_DESCRIPTION.'
                    WHEN LOWER(COALESCE(products.description, \'\')) LIKE ? THEN '.self::SCORE_DESCRIPTION.'
                    ELSE 0
                END DESC',
                [$normalized, $like, $like, $like, $like, $like, $like, $like],
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
        if ($product->relationLoaded('category') && $product->category && $like($product->category->name)) {
            $matched[] = 'category';
        }
        if ($product->relationLoaded('catalogProductType') && $product->catalogProductType && $like($product->catalogProductType->name)) {
            $matched[] = 'type';
        }
        if ($like($product->short_description)) {
            $matched[] = 'short_description';
        }
        if ($like($product->description)) {
            $matched[] = 'description';
        }

        return $matched;
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
        if (in_array('category', $matched, true) || in_array('type', $matched, true)) {
            return self::SCORE_CATEGORY_OR_TYPE;
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
}
