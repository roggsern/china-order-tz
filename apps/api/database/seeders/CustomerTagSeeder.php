<?php

namespace Database\Seeders;

use App\Models\CustomerTag;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CustomerTagSeeder extends Seeder
{
    public function run(): void
    {
        $tags = [
            ['name' => 'VIP', 'description' => 'High-priority relationship customers'],
            ['name' => 'Wholesale', 'description' => 'Wholesale purchasing behavior'],
            ['name' => 'Retail', 'description' => 'Retail customers'],
            ['name' => 'High Value', 'description' => 'High lifetime spend'],
            ['name' => 'Frequent Buyer', 'description' => 'Frequent repeat purchasers'],
        ];

        foreach ($tags as $tag) {
            CustomerTag::query()->updateOrCreate(
                ['slug' => Str::slug($tag['name'])],
                [
                    'name' => $tag['name'],
                    'description' => $tag['description'],
                    'is_active' => true,
                ],
            );
        }
    }
}
