<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $catalog = [
            'Electronics' => ['Smartphones', 'Laptops', 'Accessories'],
            'Fashion' => ['Men', 'Women', 'Kids'],
            'Home & Living' => ['Kitchen', 'Furniture', 'Decor'],
            'Beauty' => ['Skincare', 'Makeup', 'Fragrances'],
        ];

        $sortOrder = 0;

        foreach ($catalog as $parentName => $children) {
            $parent = Category::query()->updateOrCreate(
                ['slug' => Str::slug($parentName)],
                [
                    'name' => $parentName,
                    'description' => "{$parentName} products imported from China.",
                    'sort_order' => $sortOrder++,
                    'is_active' => true,
                ]
            );

            foreach ($children as $index => $childName) {
                Category::query()->updateOrCreate(
                    ['slug' => Str::slug($parentName.'-'.$childName)],
                    [
                        'parent_id' => $parent->id,
                        'name' => $childName,
                        'description' => "{$childName} under {$parentName}.",
                        'sort_order' => $index,
                        'is_active' => true,
                    ]
                );
            }
        }
    }
}
