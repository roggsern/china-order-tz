<?php

namespace Tests\Feature\Features;

use App\Models\Admin;
use App\Models\User;
use App\Services\Settings\SettingsService;
use App\Support\Admin\AdminPermissions;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MaintenanceModeEnforcementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SettingsSeeder::class);
        Cache::flush();
    }

    public function test_maintenance_enabled_blocks_storefront_catalog(): void
    {
        $this->enableMaintenance('Back soon — scheduled maintenance.');

        $this->getJson('/api/v1/products')
            ->assertStatus(503)
            ->assertJsonPath('success', false)
            ->assertJsonPath('maintenance', true)
            ->assertJsonPath('code', 'maintenance_mode')
            ->assertJsonPath('message', 'Back soon — scheduled maintenance.')
            ->assertJsonMissingPath('data.settings')
            ->assertJsonMissingPath('enabled_features');
    }

    public function test_maintenance_enabled_blocks_customer_auth_and_cart(): void
    {
        $this->enableMaintenance('Closed for upgrades.');

        $this->postJson('/api/v1/login', [
            'email' => 'customer@example.com',
            'password' => 'secret',
        ])
            ->assertStatus(503)
            ->assertJsonPath('maintenance', true);

        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/v1/cart')
            ->assertStatus(503)
            ->assertJsonPath('maintenance', true)
            ->assertJsonPath('message', 'Closed for upgrades.');
    }

    public function test_maintenance_disabled_allows_storefront_access(): void
    {
        $this->getJson('/api/v1/products')->assertOk();
        $this->getJson('/api/v1/storefront/maintenance')
            ->assertOk()
            ->assertJsonPath('data.maintenance', false)
            ->assertJsonPath('data.message', null);
    }

    public function test_maintenance_uses_default_public_message_when_empty(): void
    {
        $this->enableMaintenance('');

        $this->getJson('/api/v1/categories')
            ->assertStatus(503)
            ->assertJsonPath(
                'message',
                'The store is temporarily unavailable for maintenance. Please try again shortly.',
            );
    }

    public function test_admin_routes_unaffected_during_maintenance(): void
    {
        $this->enableMaintenance('Store closed.');

        $login = $this->postJson('/api/v1/admin/login', [
            'email' => 'admin@example.com',
            'password' => 'wrong',
        ]);

        $this->assertNotSame(503, $login->status());
        $this->assertArrayNotHasKey('maintenance', $login->json());

        Sanctum::actingAs(
            Admin::factory()->withPermissions([AdminPermissions::FEATURES_VIEW])->create(),
        );

        $this->getJson('/api/v1/admin/features/config')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.maintenance_mode', true);
    }

    public function test_health_unaffected_during_maintenance(): void
    {
        $this->enableMaintenance('Store closed.');

        $this->getJson('/api/v1/health')
            ->assertSuccessful()
            ->assertJsonMissingPath('maintenance');
    }

    public function test_public_maintenance_probe_remains_available(): void
    {
        $this->enableMaintenance('Probe message.');

        $this->getJson('/api/v1/storefront/maintenance')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.maintenance', true)
            ->assertJsonPath('data.message', 'Probe message.');
    }

    private function enableMaintenance(string $message): void
    {
        $settings = app(SettingsService::class);
        $settings->set('features.maintenance_mode', true);
        $settings->set('features.maintenance_message', $message);
        Cache::flush();
    }
}
