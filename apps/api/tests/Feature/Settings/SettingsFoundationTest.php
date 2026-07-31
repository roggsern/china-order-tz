<?php

namespace Tests\Feature\Settings;

use App\Enums\ActivityEventType;
use App\Models\ActivityLog;
use App\Models\Admin;
use App\Models\Setting;
use App\Models\User;
use App\Services\Settings\SettingsCache;
use App\Services\Settings\SettingsService;
use App\Support\Admin\AdminPermissions;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SettingsFoundationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SettingsSeeder::class);
        Cache::flush();
    }

    public function test_guest_and_customer_cannot_access_settings(): void
    {
        $this->getJson('/api/v1/admin/settings')->assertUnauthorized();

        Sanctum::actingAs(User::factory()->create());
        $this->getJson('/api/v1/admin/settings')->assertUnauthorized();
        $this->putJson('/api/v1/admin/settings/features', [
            'values' => ['maintenance_mode' => true],
        ])->assertUnauthorized();
    }

    public function test_permission_denied_without_settings_view_or_manage(): void
    {
        Sanctum::actingAs(Admin::factory()->withoutPermissions()->create());

        $this->getJson('/api/v1/admin/settings')->assertForbidden();
        $this->getJson('/api/v1/admin/settings/features')->assertForbidden();
        $this->putJson('/api/v1/admin/settings/features', [
            'values' => ['maintenance_mode' => true],
        ])->assertForbidden();
    }

    public function test_view_permission_can_list_but_not_update(): void
    {
        Sanctum::actingAs(
            Admin::factory()->withPermissions([AdminPermissions::SETTINGS_VIEW])->create(),
        );

        $this->getJson('/api/v1/admin/settings')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => [['group', 'key', 'value', 'type', 'updated_at', 'updated_by']]]);

        $this->getJson('/api/v1/admin/settings/features')
            ->assertOk()
            ->assertJsonPath('data.group', 'features');

        $this->putJson('/api/v1/admin/settings/features', [
            'values' => ['maintenance_mode' => true],
        ])->assertForbidden();
    }

    public function test_get_and_update_settings_with_type_casting_and_audit(): void
    {
        $admin = Admin::factory()->withPermissions([
            AdminPermissions::SETTINGS_VIEW,
            AdminPermissions::SETTINGS_MANAGE,
        ])->create();

        Sanctum::actingAs($admin);

        $this->assertFalse(app(SettingsService::class)->get('features.maintenance_mode'));

        $response = $this->putJson('/api/v1/admin/settings/features', [
            'values' => ['maintenance_mode' => true],
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.group', 'features')
            ->assertJsonPath('data.settings.0.key', 'features.maintenance_mode')
            ->assertJsonPath('data.settings.0.value', true)
            ->assertJsonPath('data.settings.0.type', 'boolean')
            ->assertJsonPath('data.settings.0.updated_by.id', $admin->id);

        $setting = Setting::query()->where('key', 'features.maintenance_mode')->firstOrFail();
        $this->assertSame('1', $setting->value);
        $this->assertTrue(app(SettingsService::class)->get('features.maintenance_mode'));

        $this->assertDatabaseHas('activity_logs', [
            'event_type' => ActivityEventType::SettingsUpdated->value,
            'actor_id' => $admin->id,
            'subject_id' => $setting->id,
        ]);

        $log = ActivityLog::query()
            ->where('event_type', ActivityEventType::SettingsUpdated->value)
            ->where('subject_id', $setting->id)
            ->latest('created_at')
            ->firstOrFail();

        $this->assertSame('features.maintenance_mode', $log->old_values['key'] ?? $log->metadata['key'] ?? null);
        $this->assertFalse((bool) ($log->old_values['value'] ?? true));
        $this->assertTrue((bool) ($log->new_values['value'] ?? false));
    }

    public function test_cache_invalidation_after_update(): void
    {
        $admin = Admin::factory()->withPermissions([
            AdminPermissions::SETTINGS_VIEW,
            AdminPermissions::SETTINGS_MANAGE,
        ])->create();

        Sanctum::actingAs($admin);

        $service = app(SettingsService::class);
        $cache = app(SettingsCache::class);

        $this->assertSame('nmb', $service->get('payments.default_provider'));
        $this->assertTrue(Cache::has($cache->keyForSetting('payments.default_provider')));

        $this->putJson('/api/v1/admin/settings/payments', [
            'values' => ['default_provider' => 'mock'],
        ])->assertOk();

        $this->assertFalse(Cache::has($cache->keyForSetting('payments.default_provider')));
        $this->assertSame('mock', $service->get('payments.default_provider'));
    }

    public function test_secret_keys_are_rejected_and_masked_in_audit_helper(): void
    {
        $admin = Admin::factory()->withPermissions([AdminPermissions::SETTINGS_MANAGE])->create();
        Sanctum::actingAs($admin);

        $this->putJson('/api/v1/admin/settings/payments', [
            'values' => ['nmb_password' => 'should-not-store'],
        ])->assertStatus(422);

        $this->assertDatabaseMissing('settings', [
            'key' => 'payments.nmb_password',
        ]);

        $audit = app(\App\Services\Settings\SettingsAuditService::class);
        $this->assertSame('[REDACTED]', $audit->safeValue('webhook_secret', 'abc123'));
        $this->assertSame(
            ['token' => '[REDACTED]', 'default_provider' => 'nmb'],
            $audit->maskPayload(['token' => 'abc123', 'default_provider' => 'nmb']),
        );
    }

    public function test_unknown_group_rejected(): void
    {
        Sanctum::actingAs(
            Admin::factory()->withPermissions([AdminPermissions::SETTINGS_VIEW])->create(),
        );

        $this->getJson('/api/v1/admin/settings/not-a-group')->assertStatus(422);
    }

    public function test_seeder_defaults(): void
    {
        $this->assertDatabaseHas('settings', [
            'key' => 'features.maintenance_mode',
            'group' => 'features',
            'type' => 'boolean',
            'value' => '0',
            'is_active' => true,
        ]);
        $this->assertDatabaseHas('settings', [
            'key' => 'payments.default_provider',
            'value' => 'nmb',
        ]);
        $this->assertDatabaseHas('settings', [
            'key' => 'notifications.email_enabled',
            'value' => '0',
        ]);
        $this->assertDatabaseHas('settings', [
            'key' => 'shipping.duration_source',
            'value' => 'database',
        ]);
    }
}
