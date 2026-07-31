<?php

namespace Tests\Unit\Database;

use App\Models\Order;
use Database\Seeders\CoreDatabaseSeeder;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoDatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DatabaseSeederOrchestrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_seeder_skips_demo_orders_by_default(): void
    {
        putenv('RUN_DEMO_SEEDS=false');
        $_ENV['RUN_DEMO_SEEDS'] = 'false';

        $this->seed(DatabaseSeeder::class);

        $this->assertSame(
            0,
            Order::query()
                ->whereIn('order_number', ['COT-FUL-LOCAL', 'COT-FUL-CHINA'])
                ->count(),
        );
    }

    public function test_database_seeder_includes_demo_orders_when_flag_enabled(): void
    {
        putenv('RUN_DEMO_SEEDS=true');
        $_ENV['RUN_DEMO_SEEDS'] = 'true';

        $this->seed(DatabaseSeeder::class);

        $this->assertGreaterThanOrEqual(
            1,
            Order::query()
                ->whereIn('order_number', ['COT-FUL-LOCAL', 'COT-FUL-CHINA'])
                ->count(),
        );
    }

    public function test_core_seeder_runs_without_demo_transactional_seeders(): void
    {
        $this->seed(CoreDatabaseSeeder::class);

        $this->assertSame(
            0,
            Order::query()
                ->whereIn('order_number', ['COT-FUL-LOCAL', 'COT-FUL-CHINA'])
                ->count(),
        );
    }

    public function test_demo_seeder_creates_fulfillment_demo_orders(): void
    {
        $this->seed(CoreDatabaseSeeder::class);
        $this->seed(DemoDatabaseSeeder::class);

        $this->assertGreaterThanOrEqual(
            1,
            Order::query()
                ->whereIn('order_number', ['COT-FUL-LOCAL', 'COT-FUL-CHINA'])
                ->count(),
        );
    }
}
