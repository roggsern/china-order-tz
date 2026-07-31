<?php

namespace Tests\Feature\Settings;

use App\Enums\ActivityActorType;
use App\Enums\ActivityEventType;
use App\Models\ActivityLog;
use App\Models\Admin;
use App\Models\User;
use App\Support\Admin\AdminPermissions;
use Database\Seeders\SettingsSeeder;
use Database\Seeders\ShippingMethodSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminSettingsDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SettingsSeeder::class);
        $this->seed(ShippingMethodSeeder::class);
        Cache::flush();
    }

    public function test_guest_and_customer_cannot_access_settings_dashboard_or_history(): void
    {
        $this->getJson('/api/v1/admin/settings/dashboard')->assertUnauthorized();
        $this->getJson('/api/v1/admin/settings/history')->assertUnauthorized();

        Sanctum::actingAs(User::factory()->create());
        $this->getJson('/api/v1/admin/settings/dashboard')->assertUnauthorized();
        $this->getJson('/api/v1/admin/settings/history')->assertUnauthorized();
    }

    public function test_permission_denied_without_settings_view(): void
    {
        Sanctum::actingAs(Admin::factory()->withoutPermissions()->create());

        $this->getJson('/api/v1/admin/settings/dashboard')->assertForbidden();
        $this->getJson('/api/v1/admin/settings/history')->assertForbidden();
    }

    public function test_dashboard_returns_health_modules_actions_and_recent_changes(): void
    {
        $admin = Admin::factory()->withPermissions([AdminPermissions::SETTINGS_VIEW])->create();
        Sanctum::actingAs($admin);

        ActivityLog::factory()->create([
            'event_type' => ActivityEventType::PaymentConfigurationUpdated,
            'action' => ActivityEventType::PaymentConfigurationUpdated->defaultAction(),
            'actor_type' => ActivityActorType::Admin,
            'actor_id' => $admin->id,
            'description' => 'Payment toggles updated',
            'old_values' => ['default_provider' => 'nmb', 'api_key' => 'secret-before'],
            'new_values' => ['default_provider' => 'mpesa', 'api_key' => 'secret-after'],
            'created_at' => now()->subMinute(),
        ]);

        $response = $this->getJson('/api/v1/admin/settings/dashboard')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'health_score',
                    'status',
                    'summary' => [
                        'critical_count',
                        'warning_count',
                        'info_count',
                        'healthy_count',
                    ],
                    'module_statuses' => [
                        ['key', 'label', 'href', 'permission', 'status', 'message', 'check_count'],
                    ],
                    'quick_actions' => [
                        ['key', 'label', 'href', 'permission'],
                    ],
                    'recent_changes' => [
                        ['actor', 'event', 'before', 'after', 'timestamp'],
                    ],
                ],
            ]);

        $score = (int) $response->json('data.health_score');
        $this->assertGreaterThanOrEqual(0, $score);
        $this->assertLessThanOrEqual(100, $score);

        $moduleKeys = collect($response->json('data.module_statuses'))->pluck('key')->all();
        foreach (['payments', 'shipping', 'notifications', 'store', 'features', 'security'] as $key) {
            $this->assertContains($key, $moduleKeys);
        }

        $recent = collect($response->json('data.recent_changes'));
        $this->assertTrue($recent->contains(
            fn (array $row) => $row['event'] === ActivityEventType::PaymentConfigurationUpdated->value,
        ));

        $payload = strtolower((string) json_encode($response->json('data')));
        $this->assertStringNotContainsString('secret-before', $payload);
        $this->assertStringNotContainsString('secret-after', $payload);
        $this->assertStringContainsString('[redacted]', $payload);
    }

    public function test_history_returns_configuration_audit_rows_and_masks_secrets(): void
    {
        $admin = Admin::factory()->withPermissions([AdminPermissions::SETTINGS_VIEW])->create();
        Sanctum::actingAs($admin);

        ActivityLog::factory()->create([
            'event_type' => ActivityEventType::SettingsUpdated,
            'action' => ActivityEventType::SettingsUpdated->defaultAction(),
            'actor_type' => ActivityActorType::Admin,
            'actor_id' => $admin->id,
            'old_values' => ['key' => 'features.maintenance_mode', 'value' => false],
            'new_values' => ['key' => 'features.maintenance_mode', 'value' => true],
            'created_at' => now()->subMinutes(2),
        ]);

        ActivityLog::factory()->create([
            'event_type' => ActivityEventType::NotificationConfigurationUpdated,
            'action' => ActivityEventType::NotificationConfigurationUpdated->defaultAction(),
            'actor_type' => ActivityActorType::Admin,
            'actor_id' => $admin->id,
            'old_values' => [
                'channels' => ['email_enabled' => false, 'webhook_secret' => 'whsec_old'],
            ],
            'new_values' => [
                'channels' => ['email_enabled' => true, 'webhook_secret' => 'whsec_new'],
            ],
            'created_at' => now()->subMinute(),
        ]);

        // Non-configuration event must be excluded.
        ActivityLog::factory()->create([
            'event_type' => ActivityEventType::ProductUpdated,
            'action' => ActivityEventType::ProductUpdated->defaultAction(),
            'actor_type' => ActivityActorType::Admin,
            'actor_id' => $admin->id,
            'old_values' => ['price' => '100'],
            'new_values' => ['price' => '200'],
            'created_at' => now(),
        ]);

        $response = $this->getJson('/api/v1/admin/settings/history')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    ['actor', 'event', 'before', 'after', 'timestamp'],
                ],
                'meta' => ['current_page', 'last_page', 'per_page', 'total'],
                'filters' => ['events'],
            ]);

        $events = collect($response->json('data'))->pluck('event')->all();
        $this->assertContains(ActivityEventType::SettingsUpdated->value, $events);
        $this->assertContains(ActivityEventType::NotificationConfigurationUpdated->value, $events);
        $this->assertNotContains(ActivityEventType::ProductUpdated->value, $events);

        $notification = collect($response->json('data'))->firstWhere(
            'event',
            ActivityEventType::NotificationConfigurationUpdated->value,
        );
        $this->assertNotNull($notification);
        $this->assertSame($admin->name, $notification['actor']['name'] ?? null);
        $this->assertSame(
            '[REDACTED]',
            $notification['before']['channels']['webhook_secret'] ?? null,
        );
        $this->assertSame(
            '[REDACTED]',
            $notification['after']['channels']['webhook_secret'] ?? null,
        );
        $this->assertTrue((bool) ($notification['after']['channels']['email_enabled'] ?? false));

        $filtered = $this->getJson('/api/v1/admin/settings/history?event=settings_updated')
            ->assertOk();
        $this->assertTrue(
            collect($filtered->json('data'))->every(
                fn (array $row) => $row['event'] === ActivityEventType::SettingsUpdated->value,
            ),
        );
    }

    public function test_settings_dashboard_and_history_routes_are_not_captured_by_group_show(): void
    {
        Sanctum::actingAs(
            Admin::factory()->withPermissions([AdminPermissions::SETTINGS_VIEW])->create(),
        );

        $this->getJson('/api/v1/admin/settings/dashboard')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['health_score', 'module_statuses']]);

        $this->getJson('/api/v1/admin/settings/history')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data', 'meta']);
    }
}
