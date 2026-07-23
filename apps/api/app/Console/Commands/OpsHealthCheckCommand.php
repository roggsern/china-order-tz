<?php

namespace App\Console\Commands;

use App\Support\Ops\OperationalHealth;
use Illuminate\Console\Command;
use Throwable;

class OpsHealthCheckCommand extends Command
{
    protected $signature = 'ops:health {--json : Output JSON}';

    protected $description = 'Run operational health probes and exit non-zero when critical checks fail.';

    public function handle(): int
    {
        try {
            $probe = OperationalHealth::probe(includeDiagnostics: ! app()->environment('production'));
            $criticalOk = (bool) ($probe['critical_ok'] ?? false);

            if ($this->option('json')) {
                $this->line(json_encode($probe, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            } else {
                $this->line('status: '.($probe['status'] ?? 'unknown'));
                $this->line('critical_ok: '.($criticalOk ? 'yes' : 'no'));

                foreach ($probe['checks'] ?? [] as $name => $ok) {
                    $label = $ok === null ? 'n/a' : ($ok ? 'ok' : 'fail');
                    $this->line(sprintf('%s: %s', $name, $label));
                }
            }

            return $criticalOk ? self::SUCCESS : self::FAILURE;
        } catch (Throwable) {
            $this->error('Health probe failed unexpectedly.');

            return self::FAILURE;
        }
    }
}
