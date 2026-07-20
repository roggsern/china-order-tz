<?php

namespace Database\Seeders;

use App\Models\Brand;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Seeds shared catalog brands (not attached to departments).
 */
class BrandSeeder extends Seeder
{
    /**
     * @return list<array{
     *     name: string,
     *     slug?: string,
     *     country?: string|null,
     *     website?: string|null,
     *     is_featured?: bool,
     *     sort_order?: int,
     *     group?: string
     * }>
     */
    public static function definitions(): array
    {
        $phones = [
            ['name' => 'Apple', 'country' => 'US', 'website' => 'https://www.apple.com', 'is_featured' => true],
            ['name' => 'Samsung', 'country' => 'KR', 'website' => 'https://www.samsung.com', 'is_featured' => true],
            ['name' => 'Xiaomi', 'country' => 'CN', 'website' => 'https://www.mi.com'],
            ['name' => 'Huawei', 'country' => 'CN', 'website' => 'https://www.huawei.com'],
            ['name' => 'Google', 'country' => 'US', 'website' => 'https://store.google.com'],
            ['name' => 'Tecno', 'country' => 'CN', 'website' => 'https://www.tecno-mobile.com'],
            ['name' => 'Infinix', 'country' => 'CN', 'website' => 'https://www.infinixmobility.com'],
            ['name' => 'OnePlus', 'country' => 'CN', 'website' => 'https://www.oneplus.com'],
            ['name' => 'Honor', 'country' => 'CN', 'website' => 'https://www.honor.com'],
        ];

        $audio = [
            ['name' => 'JBL', 'country' => 'US', 'website' => 'https://www.jbl.com', 'is_featured' => true],
            ['name' => 'Yamaha', 'country' => 'JP', 'website' => 'https://www.yamaha.com', 'is_featured' => true],
            ['name' => 'BOSE', 'country' => 'US', 'website' => 'https://www.bose.com'],
            ['name' => 'Behringer', 'country' => 'DE', 'website' => 'https://www.behringer.com'],
            ['name' => 'RCF', 'country' => 'IT', 'website' => 'https://www.rcf.it'],
            ['name' => 'Mackie', 'country' => 'US', 'website' => 'https://www.mackie.com'],
            ['name' => 'QSC', 'country' => 'US', 'website' => 'https://www.qsc.com'],
        ];

        $fashion = [
            ['name' => 'Nike', 'country' => 'US', 'website' => 'https://www.nike.com', 'is_featured' => true],
            ['name' => 'Adidas', 'country' => 'DE', 'website' => 'https://www.adidas.com', 'is_featured' => true],
            ['name' => 'Puma', 'country' => 'DE', 'website' => 'https://www.puma.com'],
            ['name' => "Levi's", 'slug' => 'levis', 'country' => 'US', 'website' => 'https://www.levi.com'],
            ['name' => 'Zara', 'country' => 'ES', 'website' => 'https://www.zara.com'],
            ['name' => 'H&M', 'slug' => 'hm', 'country' => 'SE', 'website' => 'https://www.hm.com'],
            ['name' => 'Gucci', 'country' => 'IT', 'website' => 'https://www.gucci.com'],
        ];

        $definitions = [];
        $sort = 1;

        foreach ([['phones', $phones], ['professional-audio', $audio], ['fashion', $fashion]] as [$group, $items]) {
            foreach ($items as $item) {
                $definitions[] = [
                    ...$item,
                    'group' => $group,
                    'sort_order' => $item['sort_order'] ?? $sort,
                    'is_featured' => $item['is_featured'] ?? false,
                ];
                $sort++;
            }
        }

        return $definitions;
    }

    public function run(): void
    {
        foreach (self::definitions() as $definition) {
            $slug = $definition['slug'] ?? Str::slug($definition['name']);

            Brand::query()->updateOrCreate(
                ['slug' => $slug],
                [
                    'name' => $definition['name'],
                    'logo' => $definition['logo'] ?? null,
                    'banner' => $definition['banner'] ?? null,
                    'website' => $definition['website'] ?? null,
                    'country' => $definition['country'] ?? null,
                    'description' => $definition['description'] ?? null,
                    'is_featured' => (bool) ($definition['is_featured'] ?? false),
                    'sort_order' => (int) ($definition['sort_order'] ?? 0),
                    'is_active' => true,
                ],
            );
        }
    }
}
