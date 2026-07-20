<?php

namespace Database\Support;

/**
 * Catalog Bible — foundation taxonomy for CHINA ORDER TZ.
 *
 * Source authority (until a full CSV/XLSX/JSON import arrives):
 * - MASTER_SPECIFICATION.md Homepage category grid
 * - TASK 002 approved subcategory examples (China Catalog)
 *
 * Rules:
 * - Brands are shared catalog entities (see BrandSeeder / TASK 006).
 * - Do not invent subcategories beyond the documented set.
 * - Roots without listed children stay as empty branches.
 */
final class CatalogBible
{
    /**
     * @return list<array{
     *     origin: string,
     *     name: string,
     *     slug: string,
     *     sort_order: int,
     *     children?: list<array{name: string, slug: string, sort_order: int}>
     * }>
     */
    public static function categories(): array
    {
        return [
            [
                'origin' => 'china',
                'name' => "Men's Fashion",
                'slug' => 'mens-fashion',
                'sort_order' => 10,
                'children' => [
                    ['name' => 'Shirts', 'slug' => 'mens-fashion-shirts', 'sort_order' => 10],
                    ['name' => 'Trousers', 'slug' => 'mens-fashion-trousers', 'sort_order' => 20],
                    ['name' => 'Jackets', 'slug' => 'mens-fashion-jackets', 'sort_order' => 30],
                ],
            ],
            [
                'origin' => 'china',
                'name' => "Women's Fashion",
                'slug' => 'womens-fashion',
                'sort_order' => 20,
                'children' => [
                    ['name' => 'Dresses', 'slug' => 'womens-fashion-dresses', 'sort_order' => 10],
                    ['name' => 'Tops', 'slug' => 'womens-fashion-tops', 'sort_order' => 20],
                    ['name' => 'Skirts', 'slug' => 'womens-fashion-skirts', 'sort_order' => 30],
                ],
            ],
            [
                'origin' => 'china',
                'name' => 'Electronics',
                'slug' => 'electronics',
                'sort_order' => 30,
                'children' => [
                    ['name' => 'Phones', 'slug' => 'electronics-phones', 'sort_order' => 10],
                    ['name' => 'Laptops', 'slug' => 'electronics-laptops', 'sort_order' => 20],
                    ['name' => 'Accessories', 'slug' => 'electronics-accessories', 'sort_order' => 30],
                ],
            ],
            // MASTER_SPECIFICATION homepage grid — roots only until Bible supplies children.
            [
                'origin' => 'china',
                'name' => 'Beauty',
                'slug' => 'beauty',
                'sort_order' => 40,
                'children' => [],
            ],
            [
                'origin' => 'china',
                'name' => 'Building Materials',
                'slug' => 'building-materials',
                'sort_order' => 50,
                'children' => [],
            ],
        ];
    }

    /**
     * Shared catalog brands (TASK 006). Not attached to departments.
     *
     * @return list<array{
     *     name: string,
     *     slug?: string,
     *     country?: string|null,
     *     website?: string|null,
     *     logo?: string|null,
     *     banner?: string|null,
     *     description?: string|null,
     *     is_featured?: bool,
     *     sort_order?: int
     * }>
     */
    public static function brands(): array
    {
        return \Database\Seeders\BrandSeeder::definitions();
    }
}
