<?php

namespace Database\Seeders;

use App\Models\Department;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DepartmentSeeder extends Seeder
{
    /**
     * @return list<array{name: string, icon: string}>
     */
    public static function definitions(): array
    {
        return [
            ['name' => "Men's Fashion", 'icon' => '👔'],
            ['name' => "Women's Fashion", 'icon' => '👗'],
            ['name' => 'Phones & Tablets', 'icon' => '📱'],
            ['name' => 'Computers & Office', 'icon' => '💻'],
            ['name' => 'Consumer Electronics', 'icon' => '🎧'],
            ['name' => 'Home Appliances', 'icon' => '🏠'],
            ['name' => 'Home & Furniture', 'icon' => '🛋️'],
            ['name' => 'Beauty & Personal Care', 'icon' => '💄'],
            ['name' => 'Health & Medical', 'icon' => '💊'],
            ['name' => 'Jewelry & Watches', 'icon' => '⌚'],
            ['name' => 'Sports & Outdoors', 'icon' => '⚽'],
            ['name' => 'Automotive', 'icon' => '🚗'],
            ['name' => 'Industrial & Tools', 'icon' => '🔧'],
            ['name' => 'Toys & Kids', 'icon' => '🧸'],
            ['name' => 'Pet Supplies', 'icon' => '🐾'],
            ['name' => 'Groceries', 'icon' => '🛒'],
            ['name' => 'Professional Audio', 'icon' => '🎙️'],
        ];
    }

    public function run(): void
    {
        foreach (self::definitions() as $index => $definition) {
            $slug = Str::slug($definition['name']);

            Department::query()->updateOrCreate(
                ['slug' => $slug],
                [
                    'name' => $definition['name'],
                    'icon' => $definition['icon'],
                    'image' => null,
                    'description' => null,
                    'sort_order' => $index + 1,
                    'is_active' => true,
                ],
            );
        }
    }
}
