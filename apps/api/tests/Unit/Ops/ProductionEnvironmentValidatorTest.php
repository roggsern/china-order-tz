<?php

namespace Tests\Unit\Ops;

use App\Support\Ops\ProductionEnvironmentValidator;
use Tests\TestCase;

class ProductionEnvironmentValidatorTest extends TestCase
{
    public function test_skips_checks_outside_production(): void
    {
        config(['app.env' => 'local']);

        $this->assertSame([], ProductionEnvironmentValidator::issues());
        $this->assertTrue(ProductionEnvironmentValidator::isProductionConfigHealthy());
    }

    public function test_detects_unsafe_production_configuration(): void
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

        $issues = ProductionEnvironmentValidator::issues();

        $this->assertContains('APP_DEBUG must be false in production.', $issues);
        $this->assertContains('PAYMENT_DEFAULT_GATEWAY must not be mock in production.', $issues);
        $this->assertContains('NMB_WEBHOOK_REQUIRE_SIGNATURE must be true in production.', $issues);
        $this->assertContains('MAIL_MAILER must not be log/array in production.', $issues);
        $this->assertFalse(ProductionEnvironmentValidator::isProductionConfigHealthy());
    }

    public function test_accepts_healthy_production_configuration(): void
    {
        $this->app['env'] = 'production';
        config([
            'app.env' => 'production',
            'app.debug' => false,
            'payments.default_gateway' => 'nmb',
            'payments.nmb.webhook_require_signature' => true,
            'mail.default' => 'smtp',
            'mail.mailers.smtp.host' => 'smtp.example.com',
            'mail.from.address' => 'noreply@example.com',
            'notifications.email.configured' => true,
        ]);

        $this->assertSame([], ProductionEnvironmentValidator::issues());
        $this->assertTrue(ProductionEnvironmentValidator::isMailConfigured());
    }
}
