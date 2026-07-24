<?php

namespace Database\Seeders;

use App\Models\CatalogProductType;
use App\Models\Category;
use App\Models\Department;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Seeds catalog taxonomy product types under subcategory/category parents.
 * Does not touch configuration-schema ProductType rows.
 */
class CatalogProductTypeSeeder extends Seeder
{
    /**
     * Departments whose catalog product types should be moved in place by name
     * when re-seeding (preserves IDs and avoids duplicate rows on slug changes).
     *
     * @var list<string>
     */
    private const NAME_BASED_UPSERT_DEPARTMENTS = [
        'computers-office',
        'consumer-electronics',
        'mens-fashion',
        'phones-tablets',
        'professional-audio',
        'womens-fashion',
    ];

    /**
     * @return array<string, array<string, list<string>>>
     */
    public static function definitions(): array
    {
        return [
            'mens-fashion' => [
                'T-Shirts' => [
                    'Round Neck T-Shirt',
                    'Oversized T-Shirt',
                    'Long Sleeve T-Shirt',
                ],
                'Polo Shirts' => [
                    'Polo Shirt',
                ],
                'Shirts' => [
                    'Formal Shirt',
                    'Casual Shirt',
                    'Denim Shirt',
                ],
                'Hoodies' => [
                    'Hoodie',
                ],
                'Jackets' => [
                    'Jacket',
                ],
                'Jeans' => [
                    'Jeans',
                ],
                'Trousers' => [
                    'Trousers',
                ],
                'Shorts' => [
                    'Shorts',
                ],
                'Suits' => [
                    'Suit',
                ],
                'Sneakers' => [
                    'Sneakers',
                ],
                'Formal Shoes' => [
                    'Formal Shoes',
                ],
                'Boots' => [
                    'Boots',
                ],
                'Sandals' => [
                    'Sandals',
                ],
                'Slippers' => [
                    'Slippers',
                ],
                'Backpacks' => [
                    'Backpack',
                ],
                'Messenger Bags' => [
                    'Messenger Bag',
                ],
                'Duffel Bags' => [
                    'Duffel Bag',
                ],
                'Laptop Bags' => [
                    'Laptop Bag',
                ],
                'Travel Bags' => [
                    'Travel Bag',
                ],
                'Belts' => [
                    'Belt',
                ],
                'Wallets' => [
                    'Wallet',
                ],
                'Caps' => [
                    'Cap',
                ],
                'Sunglasses' => [
                    'Sunglasses',
                ],
                'Watches' => [
                    'Watch',
                ],
            ],
            'womens-fashion' => [
                'Maxi Dresses' => [
                    'Maxi Dress',
                ],
                'Midi Dresses' => [
                    'Midi Dress',
                ],
                'Mini Dresses' => [
                    'Mini Dress',
                ],
                'Evening Dresses' => [
                    'Evening Dress',
                ],
                'Party Dresses' => [
                    'Party Dress',
                ],
                'Office Dresses' => [
                    'Office Dress',
                ],
                'Blouses' => [
                    'Blouse',
                ],
                'T-Shirts' => [
                    "Women's T-Shirt",
                ],
                'Crop Tops' => [
                    'Crop Top',
                ],
                'Bodysuits' => [
                    'Bodysuit',
                ],
                'High Heels' => [
                    'High Heels',
                ],
                'Flats' => [
                    'Flats',
                ],
                'Sneakers' => [
                    'Sneakers',
                ],
                'Sandals' => [
                    'Sandals',
                ],
                'Boots' => [
                    "Women's Boots",
                ],
                'Pencil Skirts' => [
                    'Pencil Skirt',
                ],
                'Pleated Skirts' => [
                    'Pleated Skirt',
                ],
                'Maxi Skirts' => [
                    'Maxi Skirt',
                ],
                'Jeans' => [
                    'Jeans',
                ],
                'Leggings' => [
                    'Leggings',
                ],
                'Palazzo Pants' => [
                    'Palazzo Pants',
                ],
                'Tote Bags' => [
                    'Tote Bag',
                ],
                'Shoulder Bags' => [
                    'Shoulder Bag',
                ],
                'Crossbody Bags' => [
                    'Crossbody Bag',
                ],
                'Scarves' => [
                    'Scarf',
                ],
                "Women's Belts" => [
                    "Women's Belt",
                ],
                'Sunglasses' => [
                    'Sunglasses',
                ],
            ],
            'phones-tablets' => [
                'Android Phones' => [
                    'Android Smartphone',
                ],
                'iPhones' => [
                    'iPhone',
                ],
                'Foldable Phones' => [
                    'Foldable Phone',
                ],
                'Gaming Phones' => [
                    'Gaming Phone',
                ],
                'Phone Cases' => [
                    'Phone Case',
                ],
                'Chargers' => [
                    'Charger',
                ],
                'Power Banks' => [
                    'Power Bank',
                ],
                'Screen Protectors' => [
                    'Screen Protector',
                ],
                'Cables' => [
                    'USB-A Cable',
                    'USB-C Cable',
                    'Lightning Cable',
                    'Micro USB Cable',
                    'HDMI Cable',
                    'AUX Cable',
                    'Ethernet Cable',
                    'VGA Cable',
                    'DisplayPort Cable',
                ],
                'Feature Phones' => [
                    'Feature Phone',
                ],
                'Tablets' => [
                    'Android Tablet',
                    'iPad',
                ],
            ],
            'computers-office' => [
                'Laptops' => [
                    'Gaming Laptop',
                    'Business Laptop',
                    'Ultrabook',
                ],
                'Desktop Computers' => [
                    'Tower Desktop',
                    'All-in-One PC',
                    'Mini PC',
                ],
                'Monitors' => [
                    'Office Monitor',
                    'Gaming Monitor',
                    'Professional Monitor',
                ],
                'Printers' => [
                    'Inkjet Printer',
                    'Laser Printer',
                    'Thermal Printer',
                ],
                'Computer Accessories' => [
                    'Keyboard',
                    'Mouse',
                    'USB Hub',
                    'Docking Station',
                    'Webcam',
                    'Laptop Stand',
                    'Cooling Pad',
                ],
            ],
            'consumer-electronics' => [
                'TVs' => [
                    'LED TV',
                    'OLED TV',
                    'Smart TV',
                ],
                'Audio' => [
                    'Bluetooth Speaker',
                    'Soundbar',
                    'Wireless Headphones',
                ],
                'Cameras' => [
                    'DSLR Camera',
                    'Mirrorless Camera',
                    'Action Camera',
                ],
                'Smart Devices' => [
                    'Smart Watch',
                    'Smart Speaker',
                    'Smart Home Hub',
                ],
                'Gaming' => [
                    'Gaming Console',
                    'Gaming Controller',
                    'Gaming Headset',
                ],
            ],
            'professional-audio' => [
                'Portable PA Systems' => [
                    'Portable PA System',
                ],
                'Complete Sound Systems' => [
                    'Complete Sound System',
                ],
                'Active Speakers' => [
                    'Active PA Speaker',
                ],
                'Passive Speakers' => [
                    'Passive PA Speaker',
                ],
                'Line Array Systems' => [
                    'Line Array System',
                ],
                'Analog Mixers' => [
                    'Analog Mixer',
                ],
                'Digital Mixers' => [
                    'Digital Mixer',
                ],
                'DJ Mixers' => [
                    'DJ Mixer',
                ],
                'Power Amplifiers' => [
                    'Power Amplifier',
                ],
                'Integrated Amplifiers' => [
                    'Integrated Amplifier',
                ],
                'Wired Microphones' => [
                    'Wired Microphone',
                ],
                'Wireless Microphones' => [
                    'Wireless Microphone',
                ],
                'Conference Microphones' => [
                    'Conference Microphone',
                ],
                'Speakers' => [
                    'Passive Speaker',
                    'Studio Monitor',
                    'Ceiling Speaker',
                ],
                'Audio Accessories' => [
                    'XLR Cable',
                    'Microphone Stand',
                    'Audio Interface',
                    'DI Box',
                ],
            ],
        ];
    }

    public function run(): void
    {
        foreach (self::definitions() as $departmentSlug => $parents) {
            $department = Department::query()->where('slug', $departmentSlug)->first();

            if ($department === null) {
                continue;
            }

            foreach ($parents as $parentName => $typeNames) {
                $parent = $this->resolveParent($department->id, $parentName);

                if ($parent === null) {
                    continue;
                }

                foreach ($typeNames as $index => $name) {
                    $slug = $parent->slug.'-'.Str::slug($name);
                    $attributes = [
                        'subcategory_id' => $parent->id,
                        'name' => $name,
                        'image' => null,
                        'description' => null,
                        'sort_order' => $index + 1,
                        'is_active' => true,
                    ];

                    if (in_array($departmentSlug, self::NAME_BASED_UPSERT_DEPARTMENTS, true)) {
                        $existing = CatalogProductType::query()
                            ->where('name', $name)
                            ->whereHas(
                                'subcategory',
                                fn ($query) => $query->where('department_id', $department->id),
                            )
                            ->first();

                        if ($existing !== null) {
                            $existing->update([
                                ...$attributes,
                                'slug' => $slug,
                            ]);

                            continue;
                        }
                    }

                    CatalogProductType::query()->updateOrCreate(
                        ['slug' => $slug],
                        $attributes,
                    );
                }
            }
        }
    }

    private function resolveParent(string $departmentId, string $parentName): ?Category
    {
        $subcategory = Category::query()
            ->where('department_id', $departmentId)
            ->where('name', $parentName)
            ->whereNotNull('parent_id')
            ->first();

        if ($subcategory !== null) {
            return $subcategory;
        }

        return Category::query()
            ->where('department_id', $departmentId)
            ->where('name', $parentName)
            ->whereNull('parent_id')
            ->first();
    }
}
