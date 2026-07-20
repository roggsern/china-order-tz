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
                'Shoes' => [
                    'High Heels',
                    'Flats',
                    'Sneakers',
                    'Sandals',
                    'Boots',
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
