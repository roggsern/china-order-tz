<?php

namespace App\Support\Catalog;

/**
 * Catalog Bible → database taxonomy crosswalk (ADMIN-12.12B.3 / ADMIN-12.12C.1).
 *
 * Maps navigation nodes to existing department/category subtrees without moving
 * products or renaming database categories.
 *
 * @phpstan-type CrosswalkEntry array{
 *     category_slugs?: list<string>,
 *     department_slugs?: list<string>,
 *     exclude_category_slugs?: list<string>,
 *     aggregate_of?: list<string>,
 *     slug_only_resolution?: bool
 * }
 */
final class CatalogNavigationCrosswalk
{
    /**
     * Orphan / faker roots that must never contribute to navigation visibility.
     *
     * @var list<string>
     */
    public const EXCLUDED_CATEGORY_SLUGS = [
        'consequatur-et',
    ];

    /**
     * Bible child slugs whose DB row may not be parented under the Bible root
     * (department flat collision). Resolved by slug for nav + discovery.
     *
     * @var list<string>
     */
    public const SLUG_ONLY_BIBLE_CHILDREN = [
        'womens-fashion-dresses',
        'womens-fashion-tops',
        'womens-fashion-skirts',
    ];

    /**
     * @return array<string, CrosswalkEntry>
     */
    public static function mappings(): array
    {
        return [
            // --- Electronics ---
            'electronics' => [
                'aggregate_of' => [
                    'electronics-phones',
                    'electronics-laptops',
                    'electronics-accessories',
                    'electronics-networking-power',
                    'electronics-consumer',
                    'electronics-audio',
                ],
            ],
            'electronics-phones' => [
                'category_slugs' => [
                    'electronics-phones',
                    'phones-tablets-smartphones',
                    'phones-tablets-feature-phones',
                    'phones-tablets-tablets',
                ],
            ],
            'electronics-laptops' => [
                // Precise laptop operational branch only — not the whole computers-office department.
                // Networking & Power / desktops / monitors / printers / accessories are separate.
                'category_slugs' => [
                    'electronics-laptops',
                    'computers-office-laptops',
                ],
            ],
            'electronics-accessories' => [
                'category_slugs' => [
                    'electronics-accessories',
                    'phones-tablets-phone-accessories',
                    'consumer-electronics-audio',
                    'consumer-electronics-gaming',
                ],
            ],
            'electronics-networking-power' => [
                // Customer chrome node → operational Networking & Power branch (self + descendants).
                'category_slugs' => ['computers-office-networking-power'],
            ],
            'electronics-consumer' => [
                'department_slugs' => ['consumer-electronics'],
            ],
            'electronics-audio' => [
                'department_slugs' => ['professional-audio'],
            ],

            // --- Men's fashion ---
            'mens-fashion' => [
                'department_slugs' => ['mens-fashion'],
            ],
            'mens-fashion-shirts' => [
                'category_slugs' => [
                    'mens-fashion-shirts',
                    'mens-fashion-clothing-shirts',
                ],
            ],
            'mens-fashion-trousers' => [
                'category_slugs' => [
                    'mens-fashion-trousers',
                    'mens-fashion-clothing-trousers',
                ],
            ],
            'mens-fashion-jackets' => [
                'category_slugs' => [
                    'mens-fashion-jackets',
                    'mens-fashion-clothing-jackets',
                ],
            ],

            // --- Women's fashion ---
            'womens-fashion' => [
                'department_slugs' => ['womens-fashion'],
            ],
            'womens-fashion-dresses' => [
                'category_slugs' => ['womens-fashion-dresses'],
                'slug_only_resolution' => true,
            ],
            'womens-fashion-tops' => [
                'category_slugs' => ['womens-fashion-tops'],
                'slug_only_resolution' => true,
            ],
            'womens-fashion-skirts' => [
                'category_slugs' => ['womens-fashion-skirts'],
                'slug_only_resolution' => true,
            ],

            // --- Beauty ---
            'beauty' => [
                'department_slugs' => ['beauty-personal-care'],
            ],

            // --- Home Care ---
            'home-care' => [
                'department_slugs' => ['home-care'],
            ],

            // --- Building materials (placeholder — no DB mapping) ---
            'building-materials' => [],
        ];
    }

    /**
     * @return CrosswalkEntry|null
     */
    public static function forBibleSlug(string $bibleSlug): ?array
    {
        return self::mappings()[$bibleSlug] ?? null;
    }

    /**
     * @return list<string>
     */
    public static function bibleSlugs(): array
    {
        return array_keys(self::mappings());
    }
}
