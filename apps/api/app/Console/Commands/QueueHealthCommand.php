<?php

namespace App\Console\Commands;

use App\Support\Monitoring\AlertNotifierManager;
use App\Support\Monitoring\QueueHealth;
use Illuminate\Console\Command;
use Throwable;

class QueueHealthCommand extends Command
{
    protected $signature = 'ops:queue-health {--json : Output JSON} {--alert : Emit alert when unhealthy}';

    protected $description = 'Check queue connectivity, failed/pending job thresholds, and worker heartbeat.';

    public function handle(QueueHealth $health, AlertNotifierManager $alerts): int
    {
        try {
            $probe = $health->probe();

            if ($this->option('json')) {
                $this->line(json_encode($probe, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            } else {
                foreach ($probe['checks'] as $name => $value) {
                    if (is_bool($value) || $value === null) {
                        $label = $value === null ? 'n/a' : ($value ? 'ok' : 'fail');
                    } else {
                        $label = (string) $value;
                    }
                    $this->line(sprintf('%s: %s', $name, $label));
                }

                foreach ($probe['messages'] as $message) {
                    $this->warn($message);
                }

                $this->line('status: '.($probe['ok'] ? 'ok' : 'fail'));
            }

            if (! $probe['ok'] && $this->option('alert')) {
                try {
                    $alerts->alert('Queue health degraded', 'warning', [
                        'failed_jobs' => $probe['checks']['failed_jobs'] ?? null,
                        'pending_jobs' => $probe['checks']['pending_jobs'] ?? null,
                        'worker_heartbeat' => $probe['checks']['worker_heartbeat'] ?? null,
                        'messages' => $probe['messages'],
                        'timestamp' => now()->toIso8601String(),
                    ]);
                } catch (Throwable) {
                    // Alerting must never break the command.
                }
            }

            return $probe['ok'] ? self::SUCCESS : self::FAILURE;
        } catch (Throwable) {
            $this->error('Queue health probe failed unexpectedly.');

            return self::FAILURE;
        }
    }
}
