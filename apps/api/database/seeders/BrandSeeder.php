<?php

namespace Database\Seeders;

use App\Models\Brand;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class BrandSeeder extends Seeder
{
    public function run(): void
    {
        $brands = [
            'Xiaomi', 'Huawei', 'Oppo', 'Tecno', 'Infinix',
            'Samsung', 'Apple', 'Lenovo', 'HP', 'Generic',
        ];

        foreach ($brands as $name) {
            Brand::query()->updateOrCreate(
                ['slug' => Str::slug($name)],
                [
                    'name' => $name,
                    'description' => "{$name} brand products.",
                    'is_active' => true,
                ]
            );
        }
    }
}
