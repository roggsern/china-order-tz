<?php

namespace Tests\Unit\Ops;

use App\Support\Monitoring\QueueHealth;
use App\Support\Ops\OperationalHealth;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * RC1 ops rebuild Phase 2 — OperationalHealth.
 */
class OperationalHealthTest extends TestCase
{
    public function test_probe_returns_required_shape_and_ok_when_healthy(): void
    {
        config([
            'queue.default' => 'sync',
            'monitoring.queue.bypass_worker_health' => false,
        ]);

        $probe = OperationalHealth::probe(false);

        $this->assertArrayHasKey('status', $probe);
        $this->assertArrayHasKey('critical_ok', $probe);
        $this->assertArrayHasKey('checks', $probe);
        $this->assertArrayNotHasKey('details', $probe);

        $this->assertSame(
            [
                'database',
                'queue',
                'cache',
                'storage',
                'scheduler',
                'environment',
            ],
            array_keys($probe['checks'])
        );

        $this->assertTrue($probe['critical_ok']);
        $this->assertTrue($probe['checks']['database']);
        $this->assertTrue($probe['checks']['cache']);
        $this->assertTrue($probe['checks']['storage']);
        $this->assertTrue($probe['checks']['queue']);
        $this->assertTrue($probe['checks']['environment']);
        $this->assertSame('ok', $probe['status']);
    }

    public function test_details_included_only_when_requested_and_have_no_secrets(): void
    {
        $probe = OperationalHealth::probe(true);

        $this->assertArrayHasKey('details', $probe);
        $this->assertArrayHasKey('timings_ms', $probe['details']);
        $this->assertArrayHasKey('environment', $probe['details']);
        $this->assertArrayHasKey('queue_connection', $probe['details']);

        $encoded = (string) json_encode($probe['details']);
        $this->assertStringNotContainsString('password', strtolower($encoded));
        $this->assertStringNotContainsString('secret', strtolower($encoded));
        $this->assertStringNotContainsString('MYSQL_PASSWORD', $encoded);
        $this->assertArrayNotHasKey('db_password', $probe['details']);
        $this->assertArrayNotHasKey('app_key', $probe['details']);
    }

    public function test_dead_queue_worker_soft_degrades_without_failing_critical(): void
    {
        config([
            'queue.default' => 'database',
            'monitoring.queue.bypass_worker_health' => false,
            'monitoring.queue.worker_heartbeat_ttl_seconds' => 300,
            'monitoring.queue.worker_startup_grace_seconds' => 60,
        ]);

        Cache::forget(QueueHealth::WORKER_HEARTBEAT_KEY);
        Cache::put(
            QueueHealth::WORKER_STARTED_KEY,
            now()->subMinutes(10)->toIso8601String(),
            now()->addHour()
        );

        $probe = OperationalHealth::probe();

        $this->assertTrue($probe['critical_ok']);
        $this->assertFalse($probe['checks']['queue']);
        $this->assertSame('degraded', $probe['status']);
    }

    public function test_queue_startup_grace_does_not_degrade(): void
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
            now()->toIso8601String(),
            now()->addHour()
        );

        $probe = OperationalHealth::probe();

        $this->assertTrue($probe['critical_ok']);
        $this->assertNull($probe['checks']['queue']);
        $this->assertSame('ok', $probe['status']);
    }

    public function test_probe_never_throws_to_caller(): void
    {
        $probe = OperationalHealth::probe(true);

        $this->assertIsArray($probe);
        $this->assertContains($probe['status'], ['ok', 'degraded', 'unhealthy']);
        $this->assertIsBool($probe['critical_ok']);
    }

    public function test_health_route_compatible_payload(): void
    {
        config(['queue.default' => 'sync']);

        $probe = OperationalHealth::probe(includeDiagnostics: true);

        $payload = [
            'status' => $probe['status'],
            'critical_ok' => (bool) ($probe['critical_ok'] ?? false),
            'checks' => $probe['checks'],
            'details' => $probe['details'] ?? null,
        ];

        $this->assertNotEmpty($payload['status']);
        $this->assertTrue($payload['critical_ok']);
        $this->assertIsArray($payload['checks']);
        $this->assertIsArray($payload['details']);
    }
}
