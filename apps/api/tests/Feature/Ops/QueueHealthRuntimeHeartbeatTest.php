<?php

namespace Tests\Feature\Ops;

use App\Support\Monitoring\QueueHealth;
use App\Support\Ops\OperationalHealth;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * RC1 ops rebuild Phase 3B — runtime heartbeat command (QueueHealth contract).
 */
class QueueHealthRuntimeHeartbeatTest extends TestCase
{
    public function test_runtime_heartbeat_command_updates_queue_health_keys(): void
    {
        config([
            'queue.default' => 'database',
            'monitoring.queue.bypass_worker_health' => false,
            'monitoring.queue.worker_heartbeat_ttl_seconds' => 300,
            'monitoring.queue.worker_startup_grace_seconds' => 120,
        ]);

        Cache::forget(QueueHealth::WORKER_HEARTBEAT_KEY);
        Cache::forget(QueueHealth::WORKER_STARTED_KEY);
        Cache::forget(OperationalHealth::RUNTIME_STARTED_CACHE_KEY);

        $this->artisan('ops:runtime-heartbeat')
            ->assertSuccessful()
            ->expectsOutputToContain('Runtime heartbeat updated.');

        $this->assertIsString(Cache::get(QueueHealth::WORKER_HEARTBEAT_KEY));
        $this->assertIsString(Cache::get(QueueHealth::WORKER_STARTED_KEY));
        $this->assertIsString(Cache::get(OperationalHealth::RUNTIME_STARTED_CACHE_KEY));
        $this->assertTrue(app(QueueHealth::class)->workerHeartbeat());
    }

    public function test_runtime_heartbeat_command_is_idempotent_and_never_crashes(): void
    {
        config(['queue.default' => 'sync']);

        $this->artisan('ops:runtime-heartbeat')->assertSuccessful();
        $runtimeStart = Cache::get(OperationalHealth::RUNTIME_STARTED_CACHE_KEY);

        $this->artisan('ops:runtime-heartbeat')
            ->assertSuccessful()
            ->expectsOutputToContain('Runtime heartbeat updated.');

        $this->assertSame($runtimeStart, Cache::get(OperationalHealth::RUNTIME_STARTED_CACHE_KEY));
        $this->assertTrue(app(QueueHealth::class)->workerHeartbeat());
    }

    public function test_queue_health_probe_passes_after_runtime_heartbeat(): void
    {
        config([
            'queue.default' => 'database',
            'monitoring.queue.bypass_worker_health' => false,
            'monitoring.queue.worker_heartbeat_ttl_seconds' => 300,
            'monitoring.queue.worker_startup_grace_seconds' => 120,
        ]);

        Cache::forget(QueueHealth::WORKER_HEARTBEAT_KEY);
        Cache::put(
            QueueHealth::WORKER_STARTED_KEY,
            now()->subMinutes(10)->toIso8601String(),
            now()->addHour()
        );

        $before = app(QueueHealth::class)->probe();
        $this->assertFalse($before['ok']);
        $this->assertFalse($before['checks']['worker_heartbeat']);

        $this->artisan('ops:runtime-heartbeat')->assertSuccessful();

        $after = app(QueueHealth::class)->probe();
        $this->assertTrue($after['ok']);
        $this->assertTrue($after['checks']['worker_heartbeat']);
    }
}
