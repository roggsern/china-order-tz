<?php

namespace Database\Seeders;

use App\Enums\CatalogOrigin;
use App\Models\Category;
use App\Models\Department;
use Database\Support\CatalogBible;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Seeds CatalogBible hierarchy plus department-linked starter categories.
 */
class CategorySeeder extends Seeder
{
    /**
     * @return array<string, list<string>>
     */
    public static function departmentCategories(): array
    {
        return [
            'mens-fashion' => [
                'Clothing',
                'Shoes',
                'Bags',
                'Accessories',
            ],
            'womens-fashion' => [
                'Dresses',
                'Tops',
                'Skirts',
                'Pants',
                'Shoes',
                'Hand Bags',
                'Accessories',
            ],
            'phones-tablets' => [
                'Smartphones',
                'Feature Phones',
                'Tablets',
                'Phone Accessories',
                'Chargers',
                'Power Banks',
            ],
            'computers-office' => [
                'Laptops',
                'Desktop Computers',
                'Monitors',
                'Printers',
                'Computer Accessories',
            ],
            'consumer-electronics' => [
                'TVs',
                'Audio',
                'Cameras',
                'Smart Devices',
                'Gaming',
            ],
            'professional-audio' => [
                'PA Systems',
                'Mixers',
                'Amplifiers',
                'Speakers',
                'Microphones',
                'Audio Accessories',
            ],
        ];
    }

    public function run(): void
    {
        foreach (CatalogBible::categories() as $rootDefinition) {
            $root = Category::query()->updateOrCreate(
                ['slug' => $rootDefinition['slug']],
                [
                    'parent_id' => null,
                    'origin' => CatalogOrigin::from($rootDefinition['origin']),
                    'name' => $rootDefinition['name'],
                    'sort_order' => $rootDefinition['sort_order'],
                    'is_active' => true,
                ],
            );

            foreach ($rootDefinition['children'] ?? [] as $childDefinition) {
                Category::query()->updateOrCreate(
                    ['slug' => $childDefinition['slug']],
                    [
                        'parent_id' => $root->id,
                        'origin' => $root->origin,
                        'name' => $childDefinition['name'],
                        'sort_order' => $childDefinition['sort_order'],
                        'is_active' => true,
                    ],
                );
            }
        }

        $this->seedDepartmentCategories();
    }

    private function seedDepartmentCategories(): void
    {
        // Department starter categories are admin/internal helpers.
        // They must NOT become ORDER FROM CHINA mega-menu roots (that uses CatalogBible only).
        // Keep them inactive for storefront navigation while remaining available for admin tooling.
        foreach (self::departmentCategories() as $departmentSlug => $categoryNames) {
            $department = Department::query()->where('slug', $departmentSlug)->first();

            if ($department === null) {
                continue;
            }

            foreach ($categoryNames as $index => $name) {
                $slug = $departmentSlug.'-'.Str::slug($name);

                Category::query()->updateOrCreate(
                    ['slug' => $slug],
                    [
                        'department_id' => $department->id,
                        'parent_id' => null,
                        'origin' => CatalogOrigin::China,
                        'name' => $name,
                        'image' => null,
                        'description' => null,
                        'sort_order' => $index + 1,
                        'is_active' => false,
                    ],
                );
            }
        }
    }
}
