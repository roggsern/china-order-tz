<?php

namespace Tests\Feature\Ops;

use Tests\TestCase;

class OpsProductionEnvCheckCommandTest extends TestCase
{
    public function test_production_env_check_skips_outside_production(): void
    {
        config(['app.env' => 'local']);

        $this->artisan('ops:production-env-check')
            ->assertSuccessful()
            ->expectsOutputToContain('Skipped');
    }

    public function test_production_env_check_fails_when_mail_and_payment_unsafe(): void
    {
        $this->app['env'] = 'production';
        config([
            'app.env' => 'production',
            'app.debug' => true,
            'payments.default_gateway' => 'mock',
            'payments.nmb.webhook_require_signature' => false,
            'mail.default' => 'log',
            'mail.mailers.smtp.host' => '',
            'mail.from.address' => '',
            'notifications.email.configured' => false,
        ]);

        $this->artisan('ops:production-env-check')
            ->assertFailed()
            ->expectsOutputToContain('APP_DEBUG must be false in production.');
    }

    public function test_ops_health_check_alias_runs_health_command(): void
    {
        config(['queue.default' => 'sync']);

        $this->artisan('ops:health-check')
            ->assertSuccessful()
            ->expectsOutputToContain('status: ok');
    }
}
