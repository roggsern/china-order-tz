<?php

namespace Tests\Feature\ConfigurationHealth;

use App\Models\Admin;
use App\Models\User;
use App\Services\ConfigurationHealth\ConfigurationHealthService;
use App\Services\Features\FeatureConfigurationService;
use App\Services\Payments\PaymentConfigurationService;
use App\Services\Shipping\ShippingRateService;
use App\Support\Admin\AdminPermissions;
use Database\Seeders\SettingsSeeder;
use Database\Seeders\ShippingMethodSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminConfigurationHealthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SettingsSeeder::class);
        $this->seed(ShippingMethodSeeder::class);
        Cache::flush();
    }

    public function test_guest_and_customer_cannot_access_configuration_health(): void
    {
        $this->getJson('/api/v1/admin/configuration-health')->assertUnauthorized();

        Sanctum::actingAs(User::factory()->create());
        $this->getJson('/api/v1/admin/configuration-health')->assertUnauthorized();
    }

    public function test_permission_denied_without_settings_view(): void
    {
        Sanctum::actingAs(Admin::factory()->withoutPermissions()->create());
        $this->getJson('/api/v1/admin/configuration-health')->assertForbidden();
    }

    public function test_settings_view_can_read_health_report_shape(): void
    {
        Sanctum::actingAs(
            Admin::factory()->withPermissions([AdminPermissions::SETTINGS_VIEW])->create(),
        );

        $response = $this->getJson('/api/v1/admin/configuration-health')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'overall_score',
                    'status',
                    'checks' => [
                        ['group', 'status', 'message', 'severity'],
                    ],
                    'summary' => [
                        'critical_count',
                        'warning_count',
                        'info_count',
                        'healthy_count',
                    ],
                ],
            ]);

        $score = (int) $response->json('data.overall_score');
        $this->assertGreaterThanOrEqual(0, $score);
        $this->assertLessThanOrEqual(100, $score);
        $this->assertContains($response->json('data.status'), ['healthy', 'warning', 'critical']);

        $groups = collect($response->json('data.checks'))->pluck('group')->unique()->values()->all();
        foreach (['payments', 'shipping', 'notifications', 'store', 'features', 'security'] as $group) {
            $this->assertContains($group, $groups);
        }

        $payload = json_encode($response->json('data'));
        $this->assertStringNotContainsString('password', strtolower((string) $payload));
        $this->assertStringNotContainsString('api_key', strtolower((string) $payload));
        $this->assertStringNotContainsString('webhook_secret', strtolower((string) $payload));
    }

    public function test_health_score_drops_when_maintenance_enabled(): void
    {
        $admin = Admin::factory()->withPermissions([
            AdminPermissions::SETTINGS_VIEW,
            AdminPermissions::FEATURES_MANAGE,
        ])->create();

        Sanctum::actingAs($admin);

        $before = app(ConfigurationHealthService::class)->report();

        app(FeatureConfigurationService::class)->updateConfig([
            'maintenance_mode' => true,
            'maintenance_message' => '',
        ], $admin);

        $after = app(ConfigurationHealthService::class)->report();

        $this->assertTrue(
            collect($after['checks'])->contains(
                fn (array $check) => $check['group'] === 'features' && $check['status'] === 'warning',
            ),
        );
        $this->assertLessThanOrEqual($before['overall_score'], $after['overall_score'] + 5);
        $this->assertContains($after['status'], ['warning', 'critical']);
    }

    public function test_resolver_integration_payment_and_shipping_signals(): void
    {
        $admin = Admin::factory()->withPermissions([
            AdminPermissions::SETTINGS_VIEW,
            AdminPermissions::PAYMENTS_CONFIG_MANAGE,
            AdminPermissions::SHIPPING_MANAGE,
        ])->create();

        Sanctum::actingAs($admin);

        app(PaymentConfigurationService::class)->updateConfig([
            'default_provider' => 'nmb',
            'enabled_methods' => [
                'nmb' => true,
                'mpesa' => false,
                'card' => false,
                'cash' => false,
                'bank_transfer' => false,
            ],
        ], $admin);

        // Disable air freight to force a shipping warning/critical signal.
        app(ShippingRateService::class)->updateRate(
            app(ShippingRateService::class)->resolveManagedMethod('air_freight'),
            ['active' => false],
            $admin,
        );

        $report = app(ConfigurationHealthService::class)->report();
        $groups = collect($report['checks'])->pluck('group')->unique();

        $this->assertTrue($groups->contains('payments'));
        $this->assertTrue($groups->contains('shipping'));
        $this->assertTrue(
            collect($report['checks'])->contains(
                fn (array $check) => $check['group'] === 'shipping'
                    && in_array($check['status'], ['warning', 'critical'], true),
            ),
        );
    }
}
