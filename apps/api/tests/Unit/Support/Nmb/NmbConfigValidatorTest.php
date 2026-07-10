<?php

namespace Tests\Unit\Support\Nmb;

use App\Support\Nmb\NmbConfigValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NmbConfigValidatorTest extends TestCase
{
    use RefreshDatabase;

    public function test_disabled_nmb_has_no_errors(): void
    {
        config(['services.nmb.enabled' => false]);

        $errors = app(NmbConfigValidator::class)->validate();

        $this->assertSame([], $errors);
    }

    public function test_missing_credentials_are_reported(): void
    {
        config([
            'services.nmb.enabled' => true,
            'services.nmb.base_url' => null,
            'services.nmb.merchant_id' => null,
        ]);

        $errors = app(NmbConfigValidator::class)->validate();

        $this->assertContains('NMB configuration missing: base_url', $errors);
        $this->assertContains('NMB configuration missing: merchant_id', $errors);
    }

    public function test_production_environment_rejects_sandbox_urls(): void
    {
        config([
            'services.nmb.enabled' => true,
            'services.nmb.environment' => 'production',
            'services.nmb.base_url' => 'https://sandbox.nmb.test',
            'services.nmb.merchant_id' => 'MERCHANT',
            'services.nmb.password' => 'secret',
            'services.nmb.return_url' => 'https://app.test/return',
            'services.nmb.merchant_name' => 'China Order TZ',
            'services.nmb.merchant_url' => 'https://chinaorder.test',
        ]);

        $errors = app(NmbConfigValidator::class)->validate();

        $this->assertContains('Production NMB environment cannot use sandbox or local base URLs.', $errors);
    }

    public function test_required_signature_without_secret_is_reported(): void
    {
        config([
            'services.nmb.enabled' => true,
            'services.nmb.base_url' => 'https://api.nmb.test',
            'services.nmb.merchant_id' => 'MERCHANT',
            'services.nmb.password' => 'secret',
            'services.nmb.return_url' => 'https://app.test/return',
            'services.nmb.merchant_name' => 'China Order TZ',
            'services.nmb.merchant_url' => 'https://chinaorder.test',
            'services.nmb.webhook.require_signature' => true,
            'services.nmb.webhook.secret' => null,
        ]);

        $errors = app(NmbConfigValidator::class)->validate();

        $this->assertContains('NMB webhook signature verification is required but NMB_WEBHOOK_SECRET is not configured.', $errors);
    }
}
