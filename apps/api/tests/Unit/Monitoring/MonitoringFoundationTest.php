<?php

namespace Tests\Unit\Monitoring;

use App\Support\Monitoring\AlertNotifier;
use App\Support\Monitoring\AlertNotifierManager;
use App\Support\Monitoring\ErrorMonitorManager;
use App\Support\Monitoring\LogAlertNotifier;
use App\Support\Monitoring\LogErrorMonitor;
use App\Support\Monitoring\PaymentMonitor;
use App\Support\Monitoring\QueueHealth;
use App\Support\Monitoring\SafeContextRedactor;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * RC1 ops rebuild Phase 1 — monitoring foundation (focused).
 */
class MonitoringFoundationTest extends TestCase
{
    public function test_safe_context_redactor_strips_secrets_recursively(): void
    {
        $redacted = SafeContextRedactor::redact([
            'payment_id' => 'pay_1',
            'password' => 'super-secret',
            'Authorization' => 'Bearer abc.def',
            'cookie' => 'session=xyz',
            'cvv' => '123',
            'nmb_password' => 'nmb-secret',
            'webhook_secret' => 'whsec',
            'nested' => ['api_key' => 'xyz', 'ok' => true],
        ]);

        $this->assertSame('pay_1', $redacted['payment_id']);
        $this->assertSame('[REDACTED]', $redacted['password']);
        $this->assertSame('[REDACTED]', $redacted['Authorization']);
        $this->assertSame('[REDACTED]', $redacted['cookie']);
        $this->assertSame('[REDACTED]', $redacted['cvv']);
        $this->assertSame('[REDACTED]', $redacted['nmb_password']);
        $this->assertSame('[REDACTED]', $redacted['webhook_secret']);
        $this->assertSame('[REDACTED]', $redacted['nested']['api_key']);
        $this->assertTrue($redacted['nested']['ok']);
    }

    public function test_safe_context_redactor_redacts_exception_messages(): void
    {
        $msg = SafeContextRedactor::redactString('Auth failed password=super-secret token=abc Bearer xyz');
        $this->assertStringNotContainsString('super-secret', $msg);
        $this->assertStringNotContainsString('abc', $msg);
        $this->assertStringContainsString('[REDACTED]', $msg);

        $payload = SafeContextRedactor::redactThrowable(
            new \RuntimeException('nmb_password=hunter2 api_key=k123')
        );
        $this->assertStringNotContainsString('hunter2', $payload['message']);
        $this->assertStringNotContainsString('k123', $payload['message']);
    }

    public function test_error_monitor_manager_defaults_to_log_driver(): void
    {
        config(['monitoring.error.driver' => 'log']);
        $this->assertInstanceOf(LogErrorMonitor::class, app(ErrorMonitorManager::class)->driver());
    }

    public function test_error_monitor_log_driver_captures_without_secrets(): void
    {
        $logger = \Mockery::mock();
        $logger->shouldReceive('error')
            ->once()
            ->withArgs(function (string $message, array $context): bool {
                return $message === 'monitoring.exception'
                    && ($context['token'] ?? null) === '[REDACTED]'
                    && ($context['order_reference'] ?? null) === 'ORD-1'
                    && ! str_contains((string) json_encode($context), 'should-not-appear');
            });

        Log::shouldReceive('channel')->with('stack')->andReturn($logger);

        app(ErrorMonitorManager::class)->driver('log')->capture(
            new \RuntimeException('boom'),
            ['token' => 'should-not-appear', 'order_reference' => 'ORD-1'],
        );
    }

    public function test_error_monitor_manager_capture_isolates_failures(): void
    {
        config(['monitoring.enabled' => true, 'monitoring.error.driver' => 'log']);

        Log::shouldReceive('channel')
            ->with('stack')
            ->andThrow(new \RuntimeException('log channel unavailable'));

        app(ErrorMonitorManager::class)->capture(new \RuntimeException('app error'), [
            'password' => 'nope',
        ]);

        $this->assertTrue(true);
    }

    public function test_alert_notifier_manager_defaults_to_log_driver(): void
    {
        config(['monitoring.alerts.driver' => 'log']);
        $this->assertInstanceOf(LogAlertNotifier::class, app(AlertNotifierManager::class)->driver());
    }

    public function test_alert_notifier_manager_isolates_driver_failures(): void
    {
        config(['monitoring.enabled' => true, 'monitoring.alerts.driver' => 'log']);

        $this->app->bind(LogAlertNotifier::class, fn () => new class implements AlertNotifier
        {
            public function notify(string $title, string $severity, array $context = []): void
            {
                throw new \RuntimeException('alert blew up');
            }
        });

        app(AlertNotifierManager::class)->alert('should not throw', 'warning', [
            'token' => 'secret-token',
        ]);

        $this->assertTrue(true);
    }

    public function test_queue_health_records_and_reports_heartbeat(): void
    {
        config([
            'queue.default' => 'sync',
            'monitoring.queue.bypass_worker_health' => false,
            'monitoring.queue.worker_heartbeat_ttl_seconds' => 300,
            'monitoring.queue.worker_startup_grace_seconds' => 120,
        ]);

        Cache::forget(QueueHealth::WORKER_HEARTBEAT_KEY);
        Cache::forget(QueueHealth::WORKER_STARTED_KEY);

        $health = app(QueueHealth::class);
        $health->touchWorkerHeartbeat();

        $raw = Cache::get(QueueHealth::WORKER_HEARTBEAT_KEY);
        $this->assertIsString($raw);
        $this->assertTrue($health->workerHeartbeat());

        $probe = $health->probe();
        $this->assertTrue($probe['ok']);
        $this->assertTrue($probe['checks']['worker_heartbeat']);
    }

    public function test_queue_health_detects_dead_worker_after_grace(): void
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

        $this->assertFalse(app(QueueHealth::class)->workerHeartbeat());
    }

    public function test_payment_monitor_emits_safe_callback_failure_alert(): void
    {
        $fake = new RecordingAlertNotifier;
        $this->app->instance(AlertNotifierManager::class, new class($fake) extends AlertNotifierManager
        {
            public function __construct(private RecordingAlertNotifier $recorder) {}

            public function driver(?string $name = null): AlertNotifier
            {
                return $this->recorder;
            }

            public function alert(string $message, string $severity = 'warning', array $context = []): void
            {
                $this->recorder->notify($message, $severity, $context);
            }
        });

        app(PaymentMonitor::class)->alertCallbackFailure('pay-1', 'exhausted_retries', 'REF-9');

        $this->assertCount(1, $fake->sent);
        $this->assertSame('NMB payment callback failure', $fake->sent[0]['title']);
        $this->assertSame('critical', $fake->sent[0]['severity']);
        $this->assertSame('nmb', $fake->sent[0]['context']['provider']);
        $this->assertSame('REF-9', $fake->sent[0]['context']['order_reference']);
        $this->assertArrayNotHasKey('password', $fake->sent[0]['context']);
        $this->assertArrayNotHasKey('token', $fake->sent[0]['context']);
    }
}

final class RecordingAlertNotifier implements AlertNotifier
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
