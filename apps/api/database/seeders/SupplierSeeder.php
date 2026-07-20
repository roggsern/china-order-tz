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
            $slug = Str::slug($supplier['name']);
            Supplier::query()->updateOrCreate(
                ['slug' => $slug],
                [
                    'name' => $supplier['name'],
                    'code' => Str::upper(Str::slug($supplier['name'], '_')),
                    'contact_person' => $supplier['contact_person'],
                    'email' => $slug.'@supplier.cn',
                    'phone' => '+86'.fake()->numerify('###########'),
                    'address' => fake()->streetAddress(),
                    'city' => $supplier['city'],
                    'country' => 'China',
                    'payment_terms' => 'Net 30',
                    'is_active' => true,
                ]
            );
        }

        // Extra demo suppliers once only — avoid growth on every boot seed.
        if (Supplier::query()->count() <= count($suppliers)) {
            Supplier::factory(2)->create();
        }
    }
}
