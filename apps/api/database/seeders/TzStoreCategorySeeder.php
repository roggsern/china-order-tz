<?php

namespace Database\Seeders;

use App\Enums\CatalogOrigin;
use App\Models\Category;
use App\Models\Store;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Store-scoped categories for BUY FROM TZ retail units.
 * Does not modify China import taxonomy.
 */
class TzStoreCategorySeeder extends Seeder
{
    public function run(): void
    {
        $trees = [
            'zion-mode' => [
                'Dresses',
                'Shoes',
                'Bags',
                'Fashion Accessories',
            ],
            'peachy-lingerie' => [
                'Bras',
                'Panties',
                'Nightwear',
                'Shapewear',
            ],
            'tzur-jewelry' => [
                'Necklaces',
                'Earrings',
                'Bracelets',
                'Rings',
            ],
            'rovi-beauty' => [
                ['name' => 'Wigs', 'slug' => 'rovi-beauty-wigs'],
                ['name' => 'Hair Care', 'slug' => 'rovi-beauty-hair-care'],
                ['name' => 'Skin Care', 'slug' => 'rovi-beauty-skincare'],
                ['name' => 'Body Lotion', 'slug' => 'rovi-beauty-lotions'],
                ['name' => 'Makeup', 'slug' => 'rovi-beauty-makeup'],
                ['name' => 'Perfume', 'slug' => 'rovi-beauty-perfume'],
                ['name' => 'Beauty Accessories', 'slug' => 'rovi-beauty-beauty-accessories'],
            ],
        ];

        foreach ($trees as $storeSlug => $names) {
            $store = Store::query()->where('slug', $storeSlug)->first();
            if ($store === null) {
                continue;
            }

            foreach (array_values($names) as $index => $entry) {
                $name = is_array($entry) ? $entry['name'] : $entry;
                $slug = is_array($entry)
                    ? $entry['slug']
                    : Str::slug($storeSlug.'-'.Str::slug($name));

                Category::query()->updateOrCreate(
                    ['slug' => $slug],
                    [
                        'store_id' => $store->id,
                        'name' => $name,
                        'parent_id' => null,
                        'origin' => CatalogOrigin::Tz,
                        'is_active' => true,
                        'sort_order' => $index + 1,
                        'description' => $name.' at '.$store->name,
                    ],
                );
            }
        }
    }
}
