<?php

namespace Tests\Feature\Notifications;

use App\Enums\ActivityEventType;
use App\Enums\NotificationChannel;
use App\Enums\NotificationEventType;
use App\Models\ActivityLog;
use App\Models\Admin;
use App\Models\User;
use App\Services\Notifications\NotificationConfigurationResolver;
use App\Services\Notifications\NotificationPlatform;
use App\Services\Notifications\DTOs\NotificationEvent;
use App\Support\Admin\AdminPermissions;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminNotificationConfigurationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SettingsSeeder::class);
        Cache::flush();
    }

    public function test_guest_and_customer_cannot_access_notification_config(): void
    {
        $this->getJson('/api/v1/admin/notifications/config')->assertUnauthorized();

        Sanctum::actingAs(User::factory()->create());
        $this->getJson('/api/v1/admin/notifications/config')->assertUnauthorized();
        $this->putJson('/api/v1/admin/notifications/config', [
            'channels' => ['email_enabled' => true],
        ])->assertUnauthorized();
    }

    public function test_permission_denied_without_notifications_view_or_manage(): void
    {
        Sanctum::actingAs(Admin::factory()->withoutPermissions()->create());

        $this->getJson('/api/v1/admin/notifications/config')->assertForbidden();
        $this->putJson('/api/v1/admin/notifications/config', [
            'channels' => ['email_enabled' => true],
        ])->assertForbidden();
    }

    public function test_view_permission_can_read_but_not_update(): void
    {
        Sanctum::actingAs(
            Admin::factory()->withPermissions([AdminPermissions::NOTIFICATIONS_VIEW])->create(),
        );

        $response = $this->getJson('/api/v1/admin/notifications/config')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.channels.in_app_enabled', true)
            ->assertJsonPath('data.channels.email_enabled', false);

        $this->assertSame(['in_app'], $response->json('data.event_channel_map')['order.created'] ?? null);

        $this->putJson('/api/v1/admin/notifications/config', [
            'channels' => ['email_enabled' => true],
        ])->assertForbidden();
    }

    public function test_manage_updates_config_and_writes_audit(): void
    {
        $admin = Admin::factory()->withPermissions([
            AdminPermissions::NOTIFICATIONS_VIEW,
            AdminPermissions::NOTIFICATIONS_MANAGE,
        ])->create();

        Sanctum::actingAs($admin);

        $response = $this->putJson('/api/v1/admin/notifications/config', [
            'channels' => [
                'email_enabled' => true,
                'sms_enabled' => false,
                'in_app_enabled' => true,
            ],
            'event_channel_map' => [
                'order.created' => ['in_app', 'email'],
                'order.paid' => ['in_app'],
                'shipment.delivered' => ['in_app'],
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.channels.email_enabled', true);

        $payload = $response->json();
        $this->assertSame(
            ['in_app', 'email'],
            $payload['data']['event_channel_map']['order.created'] ?? null,
        );

        $this->assertDatabaseHas('activity_logs', [
            'event_type' => ActivityEventType::NotificationConfigurationUpdated->value,
            'actor_id' => $admin->id,
        ]);

        $log = ActivityLog::query()
            ->where('event_type', ActivityEventType::NotificationConfigurationUpdated->value)
            ->latest('created_at')
            ->firstOrFail();

        $this->assertFalse((bool) ($log->old_values['channels']['email_enabled'] ?? true));
        $this->assertTrue((bool) ($log->new_values['channels']['email_enabled'] ?? false));
        $this->assertSame(['in_app', 'email'], $log->new_values['event_channel_map']['order.created'] ?? null);
    }

    public function test_rejects_secret_payload_keys(): void
    {
        Sanctum::actingAs(
            Admin::factory()->withPermissions([
                AdminPermissions::NOTIFICATIONS_VIEW,
                AdminPermissions::NOTIFICATIONS_MANAGE,
            ])->create(),
        );

        $this->putJson('/api/v1/admin/notifications/config', [
            'channels' => ['email_enabled' => true],
            'smtp_password' => 'secret',
        ])->assertStatus(422);

        $this->putJson('/api/v1/admin/notifications/config', [
            'event_channel_map' => [
                'order.created' => ['carrier_pigeon'],
            ],
        ])->assertStatus(422);
    }

    public function test_resolver_falls_back_when_provider_unavailable(): void
    {
        config(['notifications.email.configured' => false]);

        Sanctum::actingAs(
            Admin::factory()->withPermissions([
                AdminPermissions::NOTIFICATIONS_VIEW,
                AdminPermissions::NOTIFICATIONS_MANAGE,
            ])->create(),
        );

        $this->putJson('/api/v1/admin/notifications/config', [
            'channels' => [
                'email_enabled' => true,
                'in_app_enabled' => true,
            ],
            'event_channel_map' => [
                'order.created' => ['email'],
                'order.paid' => ['in_app'],
                'shipment.delivered' => ['in_app'],
            ],
        ])->assertOk();

        $resolver = app(NotificationConfigurationResolver::class);
        $channels = $resolver->resolveEventChannels(NotificationEventType::OrderCreated);

        $this->assertCount(1, $channels);
        $this->assertSame(NotificationChannel::InApp, $channels[0]);
    }

    public function test_resolver_integration_with_platform_dispatch(): void
    {
        Sanctum::actingAs(
            Admin::factory()->withPermissions([
                AdminPermissions::NOTIFICATIONS_VIEW,
                AdminPermissions::NOTIFICATIONS_MANAGE,
            ])->create(),
        );

        $this->putJson('/api/v1/admin/notifications/config', [
            'channels' => [
                'in_app_enabled' => true,
                'email_enabled' => false,
            ],
            'event_channel_map' => [
                'order.created' => ['in_app'],
                'order.paid' => ['in_app'],
                'shipment.delivered' => ['in_app'],
            ],
        ])->assertOk();

        $user = User::factory()->create();
        $created = app(NotificationPlatform::class)->publish(new NotificationEvent(
            type: NotificationEventType::OrderCreated,
            data: ['order_number' => 'ORD-CFG-1', 'customer_name' => 'Asha'],
            customerId: $user->id,
        ));

        $this->assertCount(1, $created);
        $this->assertSame(NotificationChannel::InApp, $created->first()->channel);
        $this->assertDatabaseHas('notifications', [
            'customer_id' => $user->id,
            'event_type' => NotificationEventType::OrderCreated->value,
            'channel' => 'in_app',
        ]);
    }
}
