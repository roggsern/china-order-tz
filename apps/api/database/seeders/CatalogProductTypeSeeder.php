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
     * @return array<string, array<string, list<string>>>
     */
    public static function definitions(): array
    {
        return [
            'mens-fashion' => [
                'T-Shirts' => [
                    'Round Neck T-Shirt',
                    'Polo Shirt',
                    'Oversized T-Shirt',
                    'Long Sleeve T-Shirt',
                ],
                'Shirts' => [
                    'Formal Shirt',
                    'Casual Shirt',
                    'Denim Shirt',
                ],
                'Shoes' => [
                    'Sneakers',
                    'Formal Shoes',
                    'Boots',
                    'Sandals',
                ],
            ],
            'womens-fashion' => [
                'Dresses' => [
                    'Maxi Dress',
                    'Midi Dress',
                    'Mini Dress',
                    'Evening Dress',
                    'Party Dress',
                ],
                'Shoes' => [
                    'High Heels',
                    'Flats',
                    'Sneakers',
                    'Sandals',
                ],
            ],
            'phones-tablets' => [
                'Smartphones' => [
                    'Android Smartphone',
                    'iPhone',
                    'Foldable Phone',
                    'Gaming Phone',
                ],
                'Phone Accessories' => [
                    'Phone Case',
                    'Charger',
                    'Power Bank',
                    'Screen Protector',
                ],
            ],
            'professional-audio' => [
                'PA Systems' => [
                    'Portable PA System',
                    'Complete Sound System',
                    'Active PA Speaker',
                    'Passive PA Speaker',
                    'Line Array System',
                ],
                'Mixers' => [
                    'Analog Mixer',
                    'Digital Mixer',
                    'DJ Mixer',
                ],
                'Amplifiers' => [
                    'Power Amplifier',
                    'Integrated Amplifier',
                ],
                'Microphones' => [
                    'Wired Microphone',
                    'Wireless Microphone',
                    'Conference Microphone',
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

                    CatalogProductType::query()->updateOrCreate(
                        ['slug' => $slug],
                        [
                            'subcategory_id' => $parent->id,
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
