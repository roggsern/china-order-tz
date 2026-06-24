<?php

namespace Database\Seeders;

use App\Models\Supplier;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class SupplierSeeder extends Seeder
{
    public function run(): void
    {
        $suppliers = [
            ['name' => 'Guangzhou Trade Co.', 'city' => 'Guangzhou', 'contact_person' => 'Li Wei'],
            ['name' => 'Shenzhen Electronics Ltd.', 'city' => 'Shenzhen', 'contact_person' => 'Zhang Ming'],
            ['name' => 'Yiwu Wholesale Hub', 'city' => 'Yiwu', 'contact_person' => 'Chen Hua'],
        ];

        foreach ($suppliers as $supplier) {
            Supplier::query()->updateOrCreate(
                ['slug' => Str::slug($supplier['name'])],
                [
                    'name' => $supplier['name'],
                    'contact_person' => $supplier['contact_person'],
                    'email' => Str::slug($supplier['name']).'@supplier.cn',
                    'phone' => '+86'.fake()->numerify('###########'),
                    'address' => fake()->streetAddress(),
                    'city' => $supplier['city'],
                    'country' => 'China',
                    'is_active' => true,
                ]
            );
        }

        Supplier::factory(2)->create();
    }
}
