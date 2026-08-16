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

    public function test_accepts_healthy_smtp_production_configuration(): void
    {
        $this->app['env'] = 'production';
        config([
            'app.env' => 'production',
            'app.debug' => false,
            'payments.default_gateway' => 'nmb',
            'payments.nmb.webhook_require_signature' => true,
            'mail.default' => 'smtp',
            'mail.mailers.smtp.host' => 'smtp.example.com',
            'mail.mailers.smtp.port' => 587,
            'mail.from.address' => 'noreply@example.com',
            'notifications.email.configured' => true,
            'services.resend.key' => '',
        ]);

        $this->assertSame([], ProductionEnvironmentValidator::issues());
        $this->assertTrue(ProductionEnvironmentValidator::isMailConfigured());
    }

    public function test_smtp_requires_mail_host_in_production(): void
    {
        $this->app['env'] = 'production';
        config([
            'app.env' => 'production',
            'app.debug' => false,
            'payments.default_gateway' => 'nmb',
            'payments.nmb.webhook_require_signature' => true,
            'mail.default' => 'smtp',
            'mail.mailers.smtp.host' => '',
            'mail.mailers.smtp.port' => 587,
            'mail.from.address' => 'orders@chinaordertz.com',
            'notifications.email.configured' => true,
        ]);

        $issues = ProductionEnvironmentValidator::mailIssues();
        $this->assertContains('MAIL_HOST is required for production SMTP email delivery.', $issues);
        $this->assertFalse(ProductionEnvironmentValidator::isMailConfigured());
    }

    public function test_accepts_healthy_resend_production_configuration_without_mail_host(): void
    {
        $this->app['env'] = 'production';
        config([
            'app.env' => 'production',
            'app.debug' => false,
            'payments.default_gateway' => 'nmb',
            'payments.nmb.webhook_require_signature' => true,
            'mail.default' => 'resend',
            'mail.mailers.smtp.host' => '',
            'mail.from.address' => 'orders@chinaordertz.com',
            'notifications.email.configured' => true,
            'services.resend.key' => 're_test_key_not_a_real_secret_value',
        ]);

        $this->assertSame([], ProductionEnvironmentValidator::mailIssues());
        $this->assertTrue(ProductionEnvironmentValidator::isMailConfigured());
        $this->assertSame([], ProductionEnvironmentValidator::issues());
    }

    public function test_resend_requires_api_key_in_production(): void
    {
        $this->app['env'] = 'production';
        config([
            'app.env' => 'production',
            'app.debug' => false,
            'payments.default_gateway' => 'nmb',
            'payments.nmb.webhook_require_signature' => true,
            'mail.default' => 'resend',
            'mail.mailers.smtp.host' => '',
            'mail.from.address' => 'orders@chinaordertz.com',
            'notifications.email.configured' => true,
            'services.resend.key' => '',
        ]);

        $issues = ProductionEnvironmentValidator::mailIssues();
        $this->assertContains(
            'RESEND_API_KEY (services.resend.key) must be set when MAIL_MAILER=resend.',
            $issues,
        );
        $this->assertFalse(ProductionEnvironmentValidator::isMailConfigured());

        // Never leak key material in issue strings (empty key case still has no secret).
        foreach ($issues as $issue) {
            $this->assertStringNotContainsString('re_', $issue);
        }
    }
}
