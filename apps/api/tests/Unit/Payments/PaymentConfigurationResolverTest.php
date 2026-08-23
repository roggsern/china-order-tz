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
            'snippe' => false,
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

    public function test_resolve_start_provider_rejects_cash_even_when_enabled(): void
    {
        app(SettingsService::class)->set('payments.enabled_methods', [
            'nmb' => true,
            'mpesa' => false,
            'card' => false,
            'cash' => true,
            'bank_transfer' => false,
        ]);
        app(SettingsService::class)->set('payments.default_provider', 'cash');
        Cache::flush();

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $this->resolver->resolveStartProvider(null);
    }

    public function test_resolve_start_provider_rejects_explicit_cash(): void
    {
        app(SettingsService::class)->set('payments.enabled_methods', [
            'nmb' => true,
            'mpesa' => false,
            'card' => false,
            'cash' => true,
            'bank_transfer' => false,
        ]);
        Cache::flush();

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $this->resolver->resolveStartProvider('cash');
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

    public function test_snippe_availability_fails_closed_without_configuration(): void
    {
        config([
            'payments.snippe.enabled' => false,
            'payments.snippe.api_key' => '',
            'payments.snippe.base_url' => '',
            'payments.snippe.webhook_secret' => '',
            'payments.snippe.webhook_url' => '',
        ]);

        $this->assertFalse($this->resolver->isProviderAvailable('snippe'));
    }

    public function test_snippe_is_not_available_without_webhook_secret(): void
    {
        $this->configureCompleteSnippe();
        config(['payments.snippe.webhook_secret' => '']);

        $this->assertFalse($this->resolver->isProviderAvailable('snippe'));
    }

    public function test_snippe_is_not_available_without_webhook_url(): void
    {
        $this->configureCompleteSnippe();
        config(['payments.snippe.webhook_url' => '']);

        $this->assertFalse($this->resolver->isProviderAvailable('snippe'));
    }

    public function test_snippe_is_not_available_with_invalid_production_webhook_url(): void
    {
        $this->app['env'] = 'production';
        $this->configureCompleteSnippe();
        config([
            'app.env' => 'production',
            'payments.snippe.webhook_url' => 'http://localhost/api/v1/payments/snippe/webhook',
        ]);

        $this->assertFalse($this->resolver->isProviderAvailable('snippe'));
    }

    public function test_complete_snippe_configuration_is_available(): void
    {
        $this->configureCompleteSnippe();

        $this->assertTrue($this->resolver->isProviderAvailable('snippe'));
    }

    public function test_snippe_is_not_selectable_when_admin_enabled_but_webhook_missing(): void
    {
        app(SettingsService::class)->set('payments.enabled_methods', [
            'nmb' => true,
            'snippe' => true,
            'mpesa' => false,
            'card' => false,
            'cash' => false,
            'bank_transfer' => false,
        ]);
        Cache::flush();

        $this->configureCompleteSnippe();
        config(['payments.snippe.webhook_secret' => '']);

        $payload = $this->resolver->presentCheckoutAvailability();
        $snippe = collect($payload['methods'])->firstWhere('code', 'snippe');

        $this->assertTrue($snippe['enabled']);
        $this->assertFalse($snippe['available']);
        $this->assertFalse($snippe['selectable']);
    }

    public function test_complete_configuration_is_selectable_when_admin_enabled(): void
    {
        app(SettingsService::class)->set('payments.enabled_methods', [
            'nmb' => true,
            'snippe' => true,
            'mpesa' => false,
            'card' => false,
            'cash' => false,
            'bank_transfer' => false,
        ]);
        Cache::flush();

        $this->configureCompleteSnippe();

        $payload = $this->resolver->presentCheckoutAvailability();
        $snippe = collect($payload['methods'])->firstWhere('code', 'snippe');

        $this->assertTrue($snippe['enabled']);
        $this->assertTrue($snippe['available']);
        $this->assertTrue($snippe['selectable']);
    }

    public function test_nmb_availability_is_independent_of_snippe_webhook_rules(): void
    {
        config([
            'payments.nmb.enabled' => true,
            'payments.nmb.merchant_id' => 'MID-1',
            'payments.nmb.base_url' => 'https://nmb.example',
            'payments.snippe.enabled' => true,
            'payments.snippe.api_key' => '',
            'payments.snippe.webhook_secret' => '',
            'payments.snippe.webhook_url' => '',
        ]);

        $this->assertTrue($this->resolver->isProviderAvailable('nmb'));
        $this->assertFalse($this->resolver->isProviderAvailable('snippe'));
    }

    private function configureCompleteSnippe(): void
    {
        config([
            'payments.snippe.enabled' => true,
            'payments.snippe.api_key' => 'test-key',
            'payments.snippe.base_url' => 'https://api.snippe.test',
            'payments.snippe.webhook_secret' => 'whsec_test',
            'payments.snippe.webhook_url' => 'https://example.test/api/v1/payments/snippe/webhook',
        ]);
    }
}
