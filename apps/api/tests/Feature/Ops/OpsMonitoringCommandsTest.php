<?php

namespace Tests\Feature\Ops;

use App\Support\Monitoring\AlertNotifier;
use App\Support\Monitoring\AlertNotifierManager;
use App\Support\Monitoring\QueueHealth;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * RC1 ops rebuild Phase 3A — monitoring console commands.
 */
class OpsMonitoringCommandsTest extends TestCase
{
    public function test_ops_health_command_succeeds_when_critical_ok(): void
    {
        config(['queue.default' => 'sync']);

        $this->artisan('ops:health')
            ->assertSuccessful()
            ->expectsOutputToContain('status: ok');
    }

    public function test_ops_health_command_fails_only_on_critical_failure(): void
    {
        config(['queue.default' => 'sync']);

        $this->artisan('ops:health --json')
            ->assertSuccessful();
    }

    public function test_queue_health_command_reports_heartbeat_and_never_crashes(): void
    {
        config([
            'queue.default' => 'sync',
            'monitoring.queue.bypass_worker_health' => false,
        ]);

        Cache::put(QueueHealth::WORKER_HEARTBEAT_KEY, now()->toIso8601String());

        $this->artisan('ops:queue-health')
            ->assertSuccessful()
            ->expectsOutputToContain('worker_heartbeat: ok');
    }

    public function test_queue_health_command_alerts_when_unhealthy_and_alert_flag_set(): void
    {
        config([
            'queue.default' => 'database',
            'monitoring.queue.bypass_worker_health' => false,
            'monitoring.queue.worker_heartbeat_ttl_seconds' => 300,
            'monitoring.queue.worker_startup_grace_seconds' => 60,
            'monitoring.enabled' => true,
        ]);

        Cache::forget(QueueHealth::WORKER_HEARTBEAT_KEY);
        Cache::put(
            QueueHealth::WORKER_STARTED_KEY,
            now()->subMinutes(10)->toIso8601String(),
            now()->addHour()
        );

        $fake = new RecordingOpsAlertNotifier;
        $this->app->instance(AlertNotifierManager::class, new class($fake) extends AlertNotifierManager
        {
            public function __construct(private RecordingOpsAlertNotifier $recorder) {}

            public function driver(?string $name = null): AlertNotifier
            {
                return $this->recorder;
            }

            public function alert(string $message, string $severity = 'warning', array $context = []): void
            {
                $this->recorder->notify($message, $severity, $context);
            }
        });

        $this->artisan('ops:queue-health --alert')
            ->assertFailed()
            ->expectsOutputToContain('status: fail');

        $this->assertCount(1, $fake->sent);
        $this->assertSame('Queue health degraded', $fake->sent[0]['title']);
    }

    public function test_monitoring_sweep_runs_without_crashing_and_emits_degraded_alert(): void
    {
        config([
            'monitoring.enabled' => true,
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

        $fake = new RecordingOpsAlertNotifier;
        $this->app->instance(AlertNotifierManager::class, new class($fake) extends AlertNotifierManager
        {
            public function __construct(private RecordingOpsAlertNotifier $recorder) {}

            public function driver(?string $name = null): AlertNotifier
            {
                return $this->recorder;
            }

            public function alert(string $message, string $severity = 'warning', array $context = []): void
            {
                $this->recorder->notify($message, $severity, $context);
            }
        });

        $this->artisan('ops:monitoring-sweep')
            ->assertSuccessful()
            ->expectsOutputToContain('Operational status: degraded')
            ->expectsOutputToContain('Queue status: fail');

        $titles = array_column($fake->sent, 'title');
        $this->assertContains('Operational health degraded', $titles);
        $this->assertContains('Queue health degraded', $titles);
    }

    public function test_monitoring_sweep_skips_when_monitoring_disabled(): void
    {
        config(['monitoring.enabled' => false]);

        $this->artisan('ops:monitoring-sweep')
            ->assertSuccessful()
            ->expectsOutputToContain('Monitoring disabled; sweep skipped.');
    }
}

final class RecordingOpsAlertNotifier implements AlertNotifier
{
    /** @var list<array{title: string, severity: string, context: array<string, mixed>}> */
    public array $sent = [];

    public function notify(string $title, string $severity, array $context = []): void
    {
        $this->sent[] = [
            'title' => $title,
            'severity' => $severity,
            'context' => $context,
        ];
    }
}
