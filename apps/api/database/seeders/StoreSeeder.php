<?php

namespace Database\Seeders;

use App\Models\Store;
use App\Services\Stores\StoreService;
use Illuminate\Database\Seeder;

class StoreSeeder extends Seeder
{
    public function run(): void
    {
        /** @var StoreService $stores */
        $stores = app(StoreService::class);

        $definitions = [
            [
                'code' => 'ZION',
                'name' => 'ZION MODE',
                'slug' => 'zion-mode',
                'description' => 'Premium women\'s fashion from Dar es Salaam',
                'theme_color' => '#1F4B3A',
                'sort_order' => 1,
                'storefront_enabled' => true,
                'storefront_visible' => true,
                'storefront_featured' => true,
                'storefront_sort_order' => 1,
            ],
            [
                'code' => 'PEACHY',
                'name' => 'PEACHY LINGERIE',
                'slug' => 'peachy-lingerie',
                'description' => 'Elegant lingerie & intimate apparel',
                'theme_color' => '#C45C7A',
                'sort_order' => 2,
                'storefront_enabled' => true,
                'storefront_visible' => true,
                'storefront_featured' => true,
                'storefront_sort_order' => 2,
            ],
            [
                'code' => 'TZUR',
                'name' => 'TZUR JEWELRY',
                'slug' => 'tzur-jewelry',
                'description' => 'Fine jewelry & statement pieces',
                'theme_color' => '#B8860B',
                'sort_order' => 3,
                'storefront_enabled' => true,
                'storefront_visible' => true,
                'storefront_featured' => true,
                'storefront_sort_order' => 3,
            ],
            [
                'code' => 'ROVI',
                'name' => 'ROVI BEAUTY',
                'slug' => 'rovi-beauty',
                'description' => 'Wigs, skincare & beauty essentials',
                'theme_color' => '#6B3FA0',
                'sort_order' => 4,
                'storefront_enabled' => true,
                'storefront_visible' => true,
                'storefront_featured' => true,
                'storefront_sort_order' => 4,
            ],
        ];

        foreach ($definitions as $row) {
            $existing = Store::query()->where('code', $row['code'])->first();
            if ($existing) {
                $stores->update($existing, $row);
                continue;
            }
            $stores->create($row);
        }
    }
}
