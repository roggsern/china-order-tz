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
                'Wigs',
                'Skincare',
                'Lotions',
                'Beauty Accessories',
            ],
        ];

        foreach ($trees as $storeSlug => $names) {
            $store = Store::query()->where('slug', $storeSlug)->first();
            if ($store === null) {
                continue;
            }

            foreach (array_values($names) as $index => $name) {
                // Global slug uniqueness — prefix with store so China taxonomy is untouched.
                $slug = Str::slug($storeSlug.'-'.Str::slug($name));
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
