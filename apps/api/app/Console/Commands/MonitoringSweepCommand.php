<?php

namespace App\Console\Commands;

use App\Support\Monitoring\AlertNotifierManager;
use App\Support\Monitoring\PaymentMonitor;
use App\Support\Monitoring\QueueHealth;
use App\Support\Ops\OperationalHealth;
use Illuminate\Console\Command;
use Throwable;

class MonitoringSweepCommand extends Command
{
    protected $signature = 'ops:monitoring-sweep';

    protected $description = 'Run operational and queue health sweeps and emit safe alerts when needed.';

    public function handle(
        AlertNotifierManager $alerts,
        QueueHealth $queueHealth,
        PaymentMonitor $paymentMonitor,
    ): int {
        try {
            if (! config('monitoring.enabled', true)) {
                $this->line('Monitoring disabled; sweep skipped.');

                return self::SUCCESS;
            }

            $health = OperationalHealth::probe(includeDiagnostics: false);
            $queue = $queueHealth->probe();
            $stuck = $paymentMonitor->checkStuckPendingPayments();

            $this->line('Operational status: '.($health['status'] ?? 'unknown'));
            $this->line('Queue status: '.($queue['ok'] ? 'ok' : 'fail'));
            $this->line('Stuck pending payments: '.($stuck['stuck_count'] ?? 0));

            $this->emitAlerts($alerts, $health, $queue, $stuck);

            return self::SUCCESS;
        } catch (Throwable) {
            try {
                $alerts->alert('Monitoring sweep failed', 'warning', [
                    'category' => 'monitoring_sweep_exception',
                    'timestamp' => now()->toIso8601String(),
                ]);
            } catch (Throwable) {
                //
            }

            $this->error('Monitoring sweep failed unexpectedly.');

            return self::SUCCESS;
        }
    }

    /**
     * @param  array<string, mixed>  $health
     * @param  array<string, mixed>  $queue
     * @param  array<string, mixed>  $stuck
     */
    private function emitAlerts(AlertNotifierManager $alerts, array $health, array $queue, array $stuck): void
    {
        try {
            $criticalOk = (bool) ($health['critical_ok'] ?? false);

            if (! $criticalOk) {
                $alerts->alert('Critical operational health failure', 'critical', [
                    'status' => $health['status'] ?? 'unhealthy',
                    'checks' => [
                        'database' => $health['checks']['database'] ?? null,
                        'storage' => $health['checks']['storage'] ?? null,
                    ],
                    'timestamp' => now()->toIso8601String(),
                ]);
            } elseif (($health['status'] ?? 'ok') === 'degraded') {
                $alerts->alert('Operational health degraded', 'warning', [
                    'status' => $health['status'],
                    'checks' => [
                        'cache' => $health['checks']['cache'] ?? null,
                        'queue' => $health['checks']['queue'] ?? null,
                        'environment' => $health['checks']['environment'] ?? null,
                    ],
                    'timestamp' => now()->toIso8601String(),
                ]);
            }

            if (! ($queue['ok'] ?? true)) {
                $alerts->alert('Queue health degraded', 'warning', [
                    'failed_jobs' => $queue['checks']['failed_jobs'] ?? null,
                    'pending_jobs' => $queue['checks']['pending_jobs'] ?? null,
                    'worker_heartbeat' => $queue['checks']['worker_heartbeat'] ?? null,
                    'messages' => $queue['messages'] ?? [],
                    'timestamp' => now()->toIso8601String(),
                ]);
            }

            if (($stuck['alerted'] ?? false) === true) {
                $this->line('Stuck payment alert emitted.');
            }
        } catch (Throwable) {
            // Never interrupt application execution for alerting.
        }
    }
}
