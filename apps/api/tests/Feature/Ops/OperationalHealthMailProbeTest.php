<?php

namespace Tests\Feature\Ops;

use App\Support\Ops\OperationalHealth;
use Tests\TestCase;

class OperationalHealthMailProbeTest extends TestCase
{
    public function test_mail_and_production_config_checks_are_null_outside_production(): void
    {
        config(['app.env' => 'local', 'queue.default' => 'sync']);

        $probe = OperationalHealth::probe();

        $this->assertNull($probe['checks']['mail']);
        $this->assertNull($probe['checks']['production_config']);
    }

    public function test_mail_check_fails_in_production_when_unconfigured(): void
    {
        $this->app['env'] = 'production';
        config([
            'app.env' => 'production',
            'app.debug' => false,
            'payments.default_gateway' => 'nmb',
            'payments.nmb.webhook_require_signature' => true,
            'mail.default' => 'log',
            'mail.mailers.smtp.host' => '',
            'mail.from.address' => '',
            'notifications.email.configured' => false,
            'queue.default' => 'sync',
        ]);

        $probe = OperationalHealth::probe();

        $this->assertFalse($probe['checks']['mail']);
        $this->assertTrue($probe['checks']['production_config']);
        $this->assertSame('degraded', $probe['status']);
    }
}
