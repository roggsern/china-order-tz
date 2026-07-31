<?php

namespace App\Console\Commands;

use App\Support\Ops\ProductionEnvironmentValidator;
use Illuminate\Console\Command;

class OpsProductionEnvCheckCommand extends Command
{
    protected $signature = 'ops:production-env-check {--json : Output JSON}';

    protected $description = 'Validate production environment safety (mail, payments, debug).';

    public function handle(): int
    {
        if (! app()->environment('production')) {
            $payload = [
                'ok' => true,
                'environment' => app()->environment(),
                'message' => 'Skipped — not running in production.',
                'issues' => [],
            ];

            if ($this->option('json')) {
                $this->line(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            } else {
                $this->info('Skipped — APP_ENV is not production.');
            }

            return self::SUCCESS;
        }

        $issues = ProductionEnvironmentValidator::issues();
        $ok = $issues === [];

        $payload = [
            'ok' => $ok,
            'environment' => app()->environment(),
            'issues' => $issues,
            'mail_configured' => ProductionEnvironmentValidator::isMailConfigured(),
        ];

        if ($this->option('json')) {
            $this->line(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $this->line('environment: production');
            $this->line('ok: '.($ok ? 'yes' : 'no'));
            $this->line('mail_configured: '.(ProductionEnvironmentValidator::isMailConfigured() ? 'yes' : 'no'));

            if ($issues === []) {
                $this->info('Production environment checks passed.');
            } else {
                foreach ($issues as $issue) {
                    $this->warn($issue);
                }
            }
        }

        return $ok ? self::SUCCESS : self::FAILURE;
    }
}
