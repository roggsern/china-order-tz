<?php

namespace Database\Seeders;

use App\Enums\CatalogOrigin;
use App\Models\Category;
use App\Models\Department;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class SubcategorySeeder extends Seeder
{
    /**
     * @return array<string, array<string, list<string>>>
     */
    public static function definitions(): array
    {
        return [
            'mens-fashion' => [
                'Clothing' => [
                    'T-Shirts',
                    'Polo Shirts',
                    'Shirts',
                    'Hoodies',
                    'Jackets',
                    'Jeans',
                    'Trousers',
                    'Shorts',
                    'Suits',
                ],
                'Shoes' => [
                    'Sneakers',
                    'Formal Shoes',
                    'Boots',
                    'Sandals',
                    'Slippers',
                ],
                'Bags' => [
                    'Backpacks',
                    'Messenger Bags',
                    'Duffel Bags',
                    'Laptop Bags',
                    'Travel Bags',
                ],
                'Accessories' => [
                    'Belts',
                    'Wallets',
                    'Caps',
                    'Sunglasses',
                    'Watches',
                ],
            ],
            'womens-fashion' => [
                'Dresses' => [
                    'Maxi Dresses',
                    'Midi Dresses',
                    'Mini Dresses',
                    'Evening Dresses',
                    'Party Dresses',
                    'Office Dresses',
                ],
                'Tops' => [
                    'Blouses',
                    'T-Shirts',
                    'Crop Tops',
                    'Bodysuits',
                ],
                'Skirts' => [
                    'Pencil Skirts',
                    'Pleated Skirts',
                    'Maxi Skirts',
                ],
                'Pants' => [
                    'Jeans',
                    'Leggings',
                    'Palazzo Pants',
                ],
                'Shoes' => [
                    'High Heels',
                    'Flats',
                    'Sneakers',
                    'Sandals',
                    'Boots',
                ],
                'Hand Bags' => [
                    'Tote Bags',
                    'Shoulder Bags',
                    'Crossbody Bags',
                ],
                'Accessories' => [
                    'Scarves',
                    "Women's Belts",
                    'Sunglasses',
                ],
            ],
            'phones-tablets' => [
                'Smartphones' => [
                    'Android Phones',
                    'iPhones',
                    'Foldable Phones',
                    'Gaming Phones',
                ],
                'Phone Accessories' => [
                    'Phone Cases',
                    'Chargers',
                    'Power Banks',
                    'Screen Protectors',
                    'Cables',
                ],
            ],
            'computers-office' => [
                'Networking & Power' => [
                    'UPS & Backup Power',
                    'DC UPS / Router Backup',
                    'Routers & Networking',
                    'Power Supplies',
                ],
            ],
            'home-appliances' => [
                'Cooking Appliances' => [
                    'Cookers & Ovens',
                    'Microwaves',
                    'Electric Stoves',
                    'Air Fryers',
                ],
                'Kitchen Appliances' => [
                    'Blenders',
                    'Mixers',
                    'Juicers',
                    'Food Processors',
                    'Electric Kettles',
                    'Coffee Makers',
                ],
            ],
            'beauty-personal-care' => [
                'Hair Care' => [
                    'Shampoo & Conditioner',
                    'Hair Treatments',
                    'Hair Styling Products',
                ],
                'Skin Care' => [
                    'Facial Cleansers',
                    'Moisturizers',
                    'Serums',
                    'Sunscreen',
                    'Face Masks',
                ],
                'Beauty Tools' => [
                    'Makeup Brushes',
                    'Mirrors',
                    'Facial Tools',
                ],
            ],
            'jewelry-watches' => [
                'Watches' => [
                    "Men's Watches",
                    "Women's Watches",
                    'Smart Watches',
                    'Watch Accessories',
                ],
            ],
            'sports-outdoors' => [
                'Fitness & Exercise' => [
                    'Gym Equipment',
                    'Yoga Equipment',
                    'Fitness Accessories',
                ],
                'Team Sports' => [
                    'Football',
                    'Basketball',
                    'Volleyball',
                ],
            ],
            'automotive' => [
                'Car Electronics' => [
                    'Car Audio',
                    'Dash Cameras',
                    'GPS & Tracking',
                    'Car Chargers',
                ],
                'Car Care' => [
                    'Cleaning Products',
                    'Polishing & Detailing',
                    'Repair & Maintenance',
                ],
            ],
            'toys-kids' => [
                'Baby Products' => [
                    'Baby Feeding',
                    'Baby Care',
                    'Strollers & Carriers',
                    'Nursery Products',
                ],
            ],
            'professional-audio' => [
                'PA Systems' => [
                    'Portable PA Systems',
                    'Complete Sound Systems',
                    'Active Speakers',
                    'Passive Speakers',
                    'Line Array Systems',
                ],
                'Mixers' => [
                    'Analog Mixers',
                    'Digital Mixers',
                    'DJ Mixers',
                ],
                'Amplifiers' => [
                    'Power Amplifiers',
                    'Integrated Amplifiers',
                ],
                'Microphones' => [
                    'Wired Microphones',
                    'Wireless Microphones',
                    'Conference Microphones',
                ],
            ],
        ];
    }

    public function run(): void
    {
        foreach (self::definitions() as $departmentSlug => $categories) {
            $department = Department::query()->where('slug', $departmentSlug)->first();

            if ($department === null) {
                continue;
            }

            foreach ($categories as $categoryName => $subcategoryNames) {
                $categorySlug = $departmentSlug.'-'.Str::slug($categoryName);
                $category = Category::query()
                    ->where('slug', $categorySlug)
                    ->whereNull('parent_id')
                    ->first();

                if ($category === null) {
                    continue;
                }

                foreach ($subcategoryNames as $index => $name) {
                    $slug = $categorySlug.'-'.Str::slug($name);

                    Category::query()->updateOrCreate(
                        ['slug' => $slug],
                        [
                            'department_id' => $department->id,
                            'parent_id' => $category->id,
                            'origin' => $category->origin ?? CatalogOrigin::China,
                            'name' => $name,
                            'image' => null,
                            'description' => null,
                            'sort_order' => $index + 1,
                            'is_active' => true,
                        ],
                    );
                }
            }
        }
    }
}
