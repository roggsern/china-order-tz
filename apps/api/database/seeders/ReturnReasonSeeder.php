<?php

namespace Database\Seeders;

use App\Models\ReturnReason;
use Illuminate\Database\Seeder;

class ReturnReasonSeeder extends Seeder
{
    public function run(): void
    {
        $reasons = [
            ['code' => 'WRONG_SIZE', 'name' => 'Wrong Size', 'sort_order' => 1],
            ['code' => 'WRONG_COLOUR', 'name' => 'Wrong Colour', 'sort_order' => 2],
            ['code' => 'DAMAGED', 'name' => 'Damaged Product', 'sort_order' => 3],
            ['code' => 'CHANGED_MIND', 'name' => 'Customer Changed Mind', 'sort_order' => 4],
            ['code' => 'WRONG_ITEM', 'name' => 'Wrong Item', 'sort_order' => 5],
            ['code' => 'DEFECTIVE', 'name' => 'Defective', 'sort_order' => 6],
            ['code' => 'OTHER', 'name' => 'Other', 'sort_order' => 99],
        ];

        foreach ($reasons as $row) {
            ReturnReason::query()->updateOrCreate(
                ['code' => $row['code']],
                [
                    'name' => $row['name'],
                    'description' => null,
                    'is_active' => true,
                    'sort_order' => $row['sort_order'],
                ],
            );
        }
    }
}
