<?php

namespace Tests\Feature\Devices;

use App\Enums\NotificationChannel;
use App\Enums\NotificationDeliveryStatus;
use App\Enums\NotificationEventType;
use App\Enums\PushTokenPlatform;
use App\Enums\PushTokenProvider;
use App\Models\Admin;
use App\Models\DevicePushToken;
use App\Models\Notification;
use App\Models\User;
use App\Services\Notifications\NotificationPlatform;
use App\Services\Notifications\Providers\PushNotificationProvider;
use App\Support\Admin\AdminPushDestinations;
use Database\Seeders\AdminPermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminDevicePushTokenTest extends TestCase
{
    use RefreshDatabase;

    private const EXPO_URL = 'https://exp.host/--/api/v2/push/send';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(AdminPermissionSeeder::class);

        config([
            'notifications.push.configured' => true,
            'notifications.push.driver' => 'expo',
            'notifications.push.expo.url' => self::EXPO_URL,
            'notifications.push.expo.access_token' => null,
            'notifications.event_channels.support_ticket_assigned' => ['in_app', 'push'],
        ]);
    }

    public function test_customer_push_registration_still_works(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $payload = $this->tokenPayload();
        $this->postJson('/api/v1/devices/push-tokens', $payload)
            ->assertCreated()
            ->assertJsonPath('data.installation_id', strtolower($payload['installation_id']));

        $row = DevicePushToken::query()->where('push_token', $payload['push_token'])->first();
        $this->assertNotNull($row);
        $this->assertSame($user->id, $row->user_id);
        $this->assertNull($row->admin_id);
    }

    public function test_admin_registers_own_token(): void
    {
        $admin = Admin::factory()->create();
        Sanctum::actingAs($admin);

        $payload = $this->tokenPayload();
        $this->postJson('/api/v1/admin/devices/push-tokens', $payload)
            ->assertCreated();

        $row = DevicePushToken::query()->where('push_token', $payload['push_token'])->firstOrFail();
        $this->assertSame($admin->id, $row->admin_id);
        $this->assertNull($row->user_id);
        $this->assertTrue($row->isAdminOwned());
    }

    public function test_unauthenticated_cannot_register_admin_token(): void
    {
        $this->postJson('/api/v1/admin/devices/push-tokens', $this->tokenPayload())
            ->assertUnauthorized();
    }

    public function test_customer_token_endpoint_cannot_create_admin_owned_token(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $payload = $this->tokenPayload();
        $payload['admin_id'] = (string) Str::uuid();

        $this->postJson('/api/v1/devices/push-tokens', $payload)->assertCreated();

        $row = DevicePushToken::query()->where('push_token', $payload['push_token'])->firstOrFail();
        $this->assertSame($user->id, $row->user_id);
        $this->assertNull($row->admin_id);
    }

    public function test_admin_token_endpoint_rejects_spoofed_admin_id(): void
    {
        $admin = Admin::factory()->create();
        $other = Admin::factory()->create();
        Sanctum::actingAs($admin);

        $payload = $this->tokenPayload();
        $payload['admin_id'] = $other->id;

        $this->postJson('/api/v1/admin/devices/push-tokens', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['admin_id']);
    }

    public function test_admin_registration_is_idempotent(): void
    {
        $admin = Admin::factory()->create();
        Sanctum::actingAs($admin);
        $payload = $this->tokenPayload();

        $this->postJson('/api/v1/admin/devices/push-tokens', $payload)->assertCreated();
        $this->postJson('/api/v1/admin/devices/push-tokens', $payload)->assertCreated();

        $this->assertSame(1, DevicePushToken::query()->where('admin_id', $admin->id)->count());
    }

    public function test_admin_token_refresh_updates_same_installation(): void
    {
        $admin = Admin::factory()->create();
        Sanctum::actingAs($admin);
        $installationId = (string) Str::uuid();
        $first = $this->tokenPayload(['installation_id' => $installationId]);
        $this->postJson('/api/v1/admin/devices/push-tokens', $first)->assertCreated();

        $second = $this->tokenPayload([
            'installation_id' => $installationId,
            'push_token' => 'ExponentPushToken['.Str::random(22).']',
        ]);
        $this->postJson('/api/v1/admin/devices/push-tokens', $second)->assertCreated();

        $this->assertSame(1, DevicePushToken::query()->where('installation_id', strtolower($installationId))->count());
        $this->assertSame(
            $second['push_token'],
            DevicePushToken::query()->where('installation_id', strtolower($installationId))->value('push_token'),
        );
    }

    public function test_customer_admin_token_collision_transfers_ownership(): void
    {
        $user = User::factory()->create();
        $admin = Admin::factory()->create();
        $token = 'ExponentPushToken['.Str::random(22).']';
        $installationId = (string) Str::uuid();

        Sanctum::actingAs($user);
        $this->postJson('/api/v1/devices/push-tokens', $this->tokenPayload([
            'push_token' => $token,
            'installation_id' => $installationId,
        ]))->assertCreated();

        Sanctum::actingAs($admin);
        $this->postJson('/api/v1/admin/devices/push-tokens', $this->tokenPayload([
            'push_token' => $token,
            'installation_id' => $installationId,
        ]))->assertCreated();

        $this->assertSame(1, DevicePushToken::query()->where('push_token', $token)->count());
        $row = DevicePushToken::query()->where('push_token', $token)->firstOrFail();
        $this->assertSame($admin->id, $row->admin_id);
        $this->assertNull($row->user_id);
    }

    public function test_admin_logout_detaches_current_installation(): void
    {
        $admin = Admin::factory()->create();
        Sanctum::actingAs($admin);
        $payload = $this->tokenPayload();
        $this->postJson('/api/v1/admin/devices/push-tokens', $payload)->assertCreated();

        $this->postJson('/api/v1/admin/logout', [
            'installation_id' => $payload['installation_id'],
        ])->assertOk();

        $this->assertSame(
            0,
            DevicePushToken::query()
                ->where('admin_id', $admin->id)
                ->where('is_active', true)
                ->count(),
        );
    }

    public function test_admin_deactivation_revokes_all_push_tokens(): void
    {
        $admin = Admin::factory()->create(['is_active' => true]);
        DevicePushToken::factory()->forAdmin($admin)->count(2)->create();

        $admin->forceFill(['is_active' => false])->save();

        $this->assertSame(
            0,
            DevicePushToken::query()->where('admin_id', $admin->id)->where('is_active', true)->count(),
        );
        $this->assertSame(
            2,
            DevicePushToken::query()->where('admin_id', $admin->id)->whereNotNull('revoked_at')->count(),
        );
    }

    public function test_notify_admin_resolves_admin_tokens_and_not_customer_tokens(): void
    {
        Http::fake([
            self::EXPO_URL => Http::response([
                'data' => [['status' => 'ok', 'id' => 'expo-admin-1']],
            ]),
        ]);

        $admin = Admin::factory()->create();
        $customer = User::factory()->create();
        DevicePushToken::factory()->forAdmin($admin)->create([
            'push_token' => 'ExponentPushToken[admindevice]',
        ]);
        DevicePushToken::factory()->create([
            'user_id' => $customer->id,
            'push_token' => 'ExponentPushToken[customerdevice]',
        ]);

        $created = app(NotificationPlatform::class)->notifyAdmin(
            NotificationEventType::SupportTicketAssigned,
            $admin,
            [
                'destination' => AdminPushDestinations::SUPPORT_TICKET,
                'ticket_id' => (string) Str::uuid(),
                'ticket_number' => 'SUP-TEST',
            ],
            channels: [NotificationChannel::Push],
            title: 'Ticket assigned',
        );

        $push = $created->firstWhere('channel', NotificationChannel::Push);
        $this->assertNotNull($push);
        $this->assertTrue(
            $push->status === NotificationDeliveryStatus::Sent
            || (string) $push->status === NotificationDeliveryStatus::Sent->value
        );
        $this->assertSame($admin->id, $push->admin_id);
        $this->assertNull($push->customer_id);

        Http::assertSent(function ($request) {
            $payload = $request->data();
            if (! is_array($payload)) {
                return false;
            }
            $tos = collect($payload)->pluck('to')->all();

            return in_array('ExponentPushToken[admindevice]', $tos, true)
                && ! in_array('ExponentPushToken[customerdevice]', $tos, true);
        });
    }

    public function test_customer_notify_path_unchanged(): void
    {
        Http::fake([
            self::EXPO_URL => Http::response([
                'data' => [['status' => 'ok', 'id' => 'expo-cust-1']],
            ]),
        ]);

        $user = User::factory()->create();
        DevicePushToken::factory()->create([
            'user_id' => $user->id,
            'push_token' => 'ExponentPushToken[custonly]',
        ]);

        $created = app(NotificationPlatform::class)->notifyCustomer(
            NotificationEventType::OrderCreated,
            $user,
            ['order_number' => 'COT-1'],
            channels: [NotificationChannel::Push],
            title: 'Order placed',
        );

        $push = $created->firstWhere('channel', NotificationChannel::Push);
        $this->assertNotNull($push);
        $this->assertTrue(
            $push->status === NotificationDeliveryStatus::Sent
            || (string) $push->status === NotificationDeliveryStatus::Sent->value
        );
        $this->assertSame($user->id, $push->customer_id);
    }

    public function test_invalid_expo_admin_token_is_revoked(): void
    {
        Http::fake([
            self::EXPO_URL => Http::response([
                'data' => [[
                    'status' => 'error',
                    'message' => 'Device not registered',
                    'details' => ['error' => 'DeviceNotRegistered'],
                ]],
            ]),
        ]);

        $admin = Admin::factory()->create();
        $device = DevicePushToken::factory()->forAdmin($admin)->create([
            'push_token' => 'ExponentPushToken[staleadmin]',
        ]);

        $notification = Notification::query()->create([
            'admin_id' => $admin->id,
            'type' => NotificationEventType::SupportTicketAssigned->value,
            'event_type' => NotificationEventType::SupportTicketAssigned->value,
            'title' => 'Assigned',
            'message' => 'Ticket assigned',
            'channel' => NotificationChannel::Push->value,
            'status' => NotificationDeliveryStatus::Processing->value,
            'provider' => 'expo',
            'data' => [
                'destination' => AdminPushDestinations::SUPPORT_TICKET,
                'ticket_id' => (string) Str::uuid(),
            ],
        ]);

        app(PushNotificationProvider::class)->send($notification);

        $device->refresh();
        $this->assertFalse($device->is_active);
        $this->assertNotNull($device->revoked_at);
    }

    public function test_admin_notification_with_no_devices_is_soft_success(): void
    {
        $admin = Admin::factory()->create();
        $notification = Notification::query()->create([
            'admin_id' => $admin->id,
            'type' => NotificationEventType::SupportTicketAssigned->value,
            'event_type' => NotificationEventType::SupportTicketAssigned->value,
            'title' => 'Assigned',
            'message' => 'Ticket assigned',
            'channel' => NotificationChannel::Push->value,
            'status' => NotificationDeliveryStatus::Processing->value,
            'provider' => 'expo',
            'data' => ['destination' => AdminPushDestinations::SUPPORT_TICKET],
        ]);

        $result = app(PushNotificationProvider::class)->send($notification);
        $this->assertTrue($result['success']);
        $this->assertSame('expo:no_devices', $result['provider_message_id']);
    }

    public function test_admin_push_payload_omits_customer_name(): void
    {
        Http::fake([
            self::EXPO_URL => Http::response([
                'data' => [['status' => 'ok', 'id' => 'expo-admin-2']],
            ]),
        ]);

        $admin = Admin::factory()->create();
        DevicePushToken::factory()->forAdmin($admin)->create([
            'push_token' => 'ExponentPushToken[adminpii]',
        ]);

        app(NotificationPlatform::class)->notifyAdmin(
            NotificationEventType::SupportTicketAssigned,
            $admin,
            [
                'destination' => AdminPushDestinations::SUPPORT_TICKET,
                'ticket_id' => (string) Str::uuid(),
                'ticket_number' => 'SUP-SAFE',
                'subject' => 'Help',
                // Even if mistakenly included, ensure destination path is present.
            ],
            channels: [NotificationChannel::Push],
            title: 'Ticket assigned',
        );

        Http::assertSent(function ($request) {
            $payload = $request->data();
            if (! is_array($payload) || $payload === []) {
                return false;
            }
            $data = $payload[0]['data'] ?? [];

            return ($data['destination'] ?? null) === AdminPushDestinations::SUPPORT_TICKET
                && ! array_key_exists('customer_name', $data)
                && ! array_key_exists('customer_email', $data);
        });
    }

    public function test_ownership_xor_constraint_rejects_both_owners(): void
    {
        $user = User::factory()->create();
        $admin = Admin::factory()->create();

        $this->expectException(\Throwable::class);

        DevicePushToken::query()->create([
            'user_id' => $user->id,
            'admin_id' => $admin->id,
            'push_token' => 'ExponentPushToken['.Str::random(22).']',
            'provider' => PushTokenProvider::Expo,
            'platform' => PushTokenPlatform::Android,
            'installation_id' => (string) Str::uuid(),
            'is_active' => true,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function tokenPayload(array $overrides = []): array
    {
        return array_merge([
            'push_token' => 'ExponentPushToken['.Str::random(22).']',
            'provider' => PushTokenProvider::Expo->value,
            'platform' => PushTokenPlatform::Android->value,
            'installation_id' => (string) Str::uuid(),
            'app_version' => '0.1.0',
            'device_name' => 'Admin Test',
        ], $overrides);
    }
}
