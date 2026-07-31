<?php

namespace Database\Seeders;

use App\Services\Settings\SettingsRepository;
use App\Services\Settings\SettingsValueCaster;
use App\Support\Settings\SettingsDefinitions;
use Illuminate\Database\Seeder;

/**
 * Idempotent defaults for the Settings foundation engine (non-secret knobs only).
 */
class SettingsSeeder extends Seeder
{
    public function run(): void
    {
        $repository = app(SettingsRepository::class);
        $caster = app(SettingsValueCaster::class);

        foreach (SettingsDefinitions::all() as $fullKey => $definition) {
            $existing = $repository->findByKey($fullKey);
            if ($existing !== null) {
                continue;
            }

            $repository->upsert([
                'key' => $fullKey,
                'group' => $definition['group'],
                'type' => $definition['type'],
                'value' => $caster->toStorage($definition['default'], $definition['type']),
                'is_active' => true,
            ]);
        }
    }
}
