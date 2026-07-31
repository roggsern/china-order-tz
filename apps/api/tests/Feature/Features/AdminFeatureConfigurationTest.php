<?php

namespace Tests\Feature\Features;

use App\Enums\ActivityEventType;
use App\Models\ActivityLog;
use App\Models\Admin;
use App\Models\User;
use App\Services\Features\FeatureFlagResolver;
use App\Services\Features\MaintenanceModeResolver;
use App\Services\Settings\SettingsCache;
use App\Services\Settings\SettingsService;
use App\Support\Admin\AdminPermissions;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminFeatureConfigurationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SettingsSeeder::class);
        Cache::flush();
    }

    public function test_guest_and_customer_cannot_access_feature_config(): void
    {
        $this->getJson('/api/v1/admin/features/config')->assertUnauthorized();

        Sanctum::actingAs(User::factory()->create());
        $this->getJson('/api/v1/admin/features/config')->assertUnauthorized();
        $this->putJson('/api/v1/admin/features/config', [
            'maintenance_mode' => true,
        ])->assertUnauthorized();
    }

    public function test_permission_denied_without_features_permissions(): void
    {
        Sanctum::actingAs(Admin::factory()->withoutPermissions()->create());

        $this->getJson('/api/v1/admin/features/config')->assertForbidden();
        $this->putJson('/api/v1/admin/features/config', [
            'maintenance_mode' => true,
        ])->assertForbidden();
    }

    public function test_view_permission_can_read_but_not_update(): void
    {
        Sanctum::actingAs(
            Admin::factory()->withPermissions([AdminPermissions::FEATURES_VIEW])->create(),
        );

        $this->getJson('/api/v1/admin/features/config')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.maintenance_mode', false)
            ->assertJsonPath('data.flags.wishlist', false)
            ->assertJsonPath('data.flags.reviews', false)
            ->assertJsonPath('data.flags.new_checkout', false);

        $this->putJson('/api/v1/admin/features/config', [
            'maintenance_mode' => true,
        ])->assertForbidden();
    }

    public function test_manage_updates_config_writes_audit_and_invalidates_cache(): void
    {
        $admin = Admin::factory()->withPermissions([
            AdminPermissions::FEATURES_VIEW,
            AdminPermissions::FEATURES_MANAGE,
        ])->create();

        Sanctum::actingAs($admin);

        $settings = app(SettingsService::class);
        $cache = app(SettingsCache::class);

        $this->assertFalse($settings->get('features.maintenance_mode'));
        $this->assertTrue(Cache::has($cache->keyForSetting('features.maintenance_mode')));

        $response = $this->putJson('/api/v1/admin/features/config', [
            'maintenance_mode' => true,
            'maintenance_message' => 'We will be back shortly.',
            'flags' => [
                'wishlist' => true,
                'reviews' => false,
                'new_checkout' => true,
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.maintenance_mode', true)
            ->assertJsonPath('data.maintenance_message', 'We will be back shortly.')
            ->assertJsonPath('data.flags.wishlist', true)
            ->assertJsonPath('data.flags.new_checkout', true);

        // Update invalidates then response presentation re-warms cache with the new value.
        $this->assertTrue(Cache::has($cache->keyForSetting('features.maintenance_mode')));
        $this->assertTrue((bool) Cache::get($cache->keyForSetting('features.maintenance_mode')));
        $this->assertTrue($settings->get('features.maintenance_mode'));
        $this->assertSame('We will be back shortly.', $settings->get('features.maintenance_message'));

        Cache::forget($cache->keyForSetting('features.maintenance_mode'));
        $this->assertTrue($settings->get('features.maintenance_mode'));

        $this->assertDatabaseHas('activity_logs', [
            'event_type' => ActivityEventType::FeatureConfigurationUpdated->value,
            'actor_id' => $admin->id,
        ]);

        $log = ActivityLog::query()
            ->where('event_type', ActivityEventType::FeatureConfigurationUpdated->value)
            ->latest('created_at')
            ->firstOrFail();

        $this->assertFalse((bool) ($log->old_values['maintenance_mode'] ?? true));
        $this->assertTrue((bool) ($log->new_values['maintenance_mode'] ?? false));
        $this->assertTrue((bool) ($log->new_values['flags']['wishlist'] ?? false));
    }

    public function test_rejects_forbidden_and_unknown_feature_flags(): void
    {
        Sanctum::actingAs(
            Admin::factory()->withPermissions([
                AdminPermissions::FEATURES_VIEW,
                AdminPermissions::FEATURES_MANAGE,
            ])->create(),
        );

        $this->putJson('/api/v1/admin/features/config', [
            'flags' => [
                'payment_verification' => true,
            ],
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['flags']);

        $this->putJson('/api/v1/admin/features/config', [
            'flags' => [
                'inventory_reservation' => true,
            ],
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['flags']);

        $this->putJson('/api/v1/admin/features/config', [
            'flags' => [
                'order_lifecycle' => false,
            ],
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['flags']);

        $this->putJson('/api/v1/admin/features/config', [
            'flags' => [
                'permissions_bypass' => true,
            ],
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['flags']);

        $this->putJson('/api/v1/admin/features/config', [
            'flags' => [
                'mystery_flag' => true,
            ],
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['flags']);
    }

    public function test_maintenance_and_flag_resolvers(): void
    {
        Sanctum::actingAs(
            Admin::factory()->withPermissions([
                AdminPermissions::FEATURES_VIEW,
                AdminPermissions::FEATURES_MANAGE,
            ])->create(),
        );

        $this->putJson('/api/v1/admin/features/config', [
            'maintenance_mode' => true,
            'maintenance_message' => 'Offline for upgrades',
            'flags' => [
                'wishlist' => true,
                'reviews' => false,
                'new_checkout' => false,
            ],
        ])->assertOk();

        $maintenance = app(MaintenanceModeResolver::class);
        $flags = app(FeatureFlagResolver::class);

        $this->assertTrue($maintenance->isEnabled());
        $this->assertSame('Offline for upgrades', $maintenance->message());
        $this->assertSame([
            'enabled' => true,
            'message' => 'Offline for upgrades',
        ], $maintenance->status());

        $this->assertTrue($flags->isEnabled('wishlist'));
        $this->assertFalse($flags->isEnabled('reviews'));
        $this->assertSame(['wishlist'], $flags->enabledFeatures());
        $this->assertFalse($flags->isEnabled('payment_verification'));
    }
}
