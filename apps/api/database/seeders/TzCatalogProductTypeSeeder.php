<?php

namespace Database\Seeders;

use App\Enums\CatalogOrigin;
use App\Models\CatalogProductType;
use App\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Catalog product types for BUY FROM TZ store categories.
 * Does not modify China import catalog product types.
 */
class TzCatalogProductTypeSeeder extends Seeder
{
    /** Canonical TZ catalog product type names. */
    public const CANONICAL_TYPES = [
        'Wigs',
        'Hair Care',
        'Skin Care',
        'Body Lotion',
        'Makeup',
        'Perfume',
        'Beauty Accessories',
    ];

    /**
     * Store category label => catalog product type name.
     *
     * @var array<string, string>
     */
    private const CATEGORY_TYPE_MAP = [
        'Wigs' => 'Wigs',
        'Hair Care' => 'Hair Care',
        'Skin Care' => 'Skin Care',
        'Skincare' => 'Skin Care',
        'Body Lotion' => 'Body Lotion',
        'Lotions' => 'Body Lotion',
        'Makeup' => 'Makeup',
        'Perfume' => 'Perfume',
        'Beauty Accessories' => 'Beauty Accessories',
    ];

    public function run(): void
    {
        $categories = Category::query()
            ->where('origin', CatalogOrigin::Tz)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        foreach ($categories as $category) {
            $typeName = self::CATEGORY_TYPE_MAP[$category->name] ?? $category->name;
            $slug = $category->slug.'-'.Str::slug($typeName);

            CatalogProductType::query()->updateOrCreate(
                ['slug' => $slug],
                [
                    'subcategory_id' => $category->id,
                    'name' => $typeName,
                    'image' => null,
                    'description' => null,
                    'sort_order' => 1,
                    'is_active' => true,
                ],
            );
        }
    }
}
