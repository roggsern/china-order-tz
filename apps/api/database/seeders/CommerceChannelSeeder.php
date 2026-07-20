<?php

namespace Database\Seeders;

use App\Enums\CommerceChannelCode;
use App\Models\CommerceChannel;
use Illuminate\Database\Seeder;

class CommerceChannelSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            [
                'code' => CommerceChannelCode::ChinaImport->value,
                'name' => CommerceChannelCode::ChinaImport->label(),
                'description' => 'Import commerce channel — air/sea shipping and customer agent delivery.',
            ],
            [
                'code' => CommerceChannelCode::TzLocal->value,
                'name' => CommerceChannelCode::TzLocal->label(),
                'description' => 'Local Tanzania commerce channel — self pickup and negotiated delivery.',
            ],
        ] as $row) {
            CommerceChannel::query()->updateOrCreate(
                ['code' => $row['code']],
                [
                    'name' => $row['name'],
                    'description' => $row['description'],
                    'is_active' => true,
                ],
            );
        }
    }
}
