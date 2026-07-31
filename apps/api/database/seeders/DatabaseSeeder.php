<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * Application database seeder entry point.
 *
 * Core infrastructure/catalog seeds always run. Demo transactional data is opt-in
 * only — set RUN_DEMO_SEEDS=true to include DemoDatabaseSeeder.
 */
class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call(CoreDatabaseSeeder::class);

        if ($this->shouldRunDemoSeeds()) {
            $this->call(DemoDatabaseSeeder::class);
        }
    }

    private function shouldRunDemoSeeds(): bool
    {
        return filter_var(env('RUN_DEMO_SEEDS', false), FILTER_VALIDATE_BOOLEAN);
    }
}
