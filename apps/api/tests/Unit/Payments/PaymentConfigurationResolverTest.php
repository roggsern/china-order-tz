<?php

namespace Tests\Unit\Payments;

use App\Services\Payments\PaymentConfigurationResolver;
use App\Services\Settings\SettingsService;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class PaymentConfigurationResolverTest extends TestCase
{
    use RefreshDatabase;

    private PaymentConfigurationResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SettingsSeeder::class);
        Cache::flush();
        $this->resolver = app(PaymentConfigurationResolver::class);
    }

    public function test_defaults_from_settings_definitions(): void
    {
        $this->assertSame('nmb', $this->resolver->resolveDefaultProvider());
        $this->assertSame([
            'nmb' => true,
            'mpesa' => false,
            'card' => false,
            'cash' => false,
            'bank_transfer' => false,
        ], $this->resolver->resolveEnabledMethods());
        $this->assertSame(['nmb'], $this->resolver->enabledMethodList());
    }

    public function test_validate_provider_availability_rejects_disabled_default(): void
    {
        app(SettingsService::class)->set('payments.enabled_methods', [
            'nmb' => false,
            'mpesa' => false,
            'card' => false,
            'cash' => true,
            'bank_transfer' => false,
        ]);
        Cache::flush();

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $this->resolver->validateProviderAvailability('nmb');
    }

    public function test_validate_provider_availability_rejects_unknown(): void
    {
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $this->resolver->validateProviderAvailability('stripe_custom');
    }

    public function test_resolve_start_provider_uses_settings_default(): void
    {
        $this->assertSame('nmb', $this->resolver->resolveStartProvider(null));
        $this->assertSame('nmb', $this->resolver->resolveStartProvider('nmb'));
    }

    public function test_resolve_start_provider_rejects_disabled(): void
    {
        app(SettingsService::class)->set('payments.enabled_methods', [
            'nmb' => false,
            'mpesa' => false,
            'card' => false,
            'cash' => true,
            'bank_transfer' => false,
        ]);
        app(SettingsService::class)->set('payments.default_provider', 'cash');
        Cache::flush();

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $this->resolver->resolveStartProvider('nmb');
    }

    public function test_present_checkout_availability_filters_enabled(): void
    {
        $payload = $this->resolver->presentCheckoutAvailability();

        $this->assertSame('nmb', $payload['default_provider']);
        $this->assertSame(['nmb'], $payload['enabled_methods']);
        $this->assertSame('nmb', $payload['methods'][0]['code']);
        $this->assertTrue($payload['methods'][0]['enabled']);
        $this->assertFalse($payload['methods'][1]['enabled']);
    }

    public function test_nmb_availability_uses_env_config_not_secrets(): void
    {
        config([
            'payments.nmb.enabled' => true,
            'payments.nmb.merchant_id' => 'MID-1',
            'payments.nmb.base_url' => 'https://nmb.example',
        ]);

        $this->assertTrue($this->resolver->isProviderAvailable('nmb'));

        config([
            'payments.nmb.enabled' => false,
        ]);

        $this->assertFalse($this->resolver->isProviderAvailable('nmb'));
    }
}
