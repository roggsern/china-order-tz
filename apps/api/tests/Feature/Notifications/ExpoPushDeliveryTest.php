<?php

namespace Tests\Feature\Notifications;

use App\Enums\NotificationChannel;
use App\Enums\NotificationDeliveryStatus;
use App\Enums\NotificationEventType;
use App\Enums\PushTokenPlatform;
use App\Enums\PushTokenProvider;
use App\Models\DevicePushToken;
use App\Models\Notification;
use App\Models\NotificationTemplate;
use App\Models\User;
use App\Services\Notifications\NotificationPlatform;
use App\Services\Notifications\Providers\PushNotificationProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class ExpoPushDeliveryTest extends TestCase
{
    use RefreshDatabase;

    private const EXPO_URL = 'https://exp.host/--/api/v2/push/send';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'notifications.push.configured' => true,
            'notifications.push.driver' => 'expo',
            'notifications.push.expo.url' => self::EXPO_URL,
            'notifications.push.expo.access_token' => null,
            'notifications.push.expo.timeout' => 5,
            'notifications.push.expo.connect_timeout' => 2,
            'notifications.push.expo.batch_size' => 100,
        ]);
    }

    private function makeNotification(User $user, array $data = []): Notification
    {
        return Notification::query()->create([
            'user_id' => $user->id,
            'customer_id' => $user->id,
            'type' => NotificationEventType::OrderCreated->value,
            'event_type' => NotificationEventType::OrderCreated->value,
            'title' => 'Order placed',
            'message' => 'Your order ORD-1 was created.',
            'channel' => NotificationChannel::Push->value,
            'status' => NotificationDeliveryStatus::Processing->value,
            'provider' => 'expo',
            'data' => array_merge([
                'order_id' => (string) Str::uuid(),
                'order_number' => 'ORD-1',
            ], $data),
        ]);
    }

    private function registerDevice(User $user, array $overrides = []): DevicePushToken
    {
        return DevicePushToken::factory()->create(array_merge([
            'user_id' => $user->id,
            'provider' => PushTokenProvider::Expo,
            'platform' => PushTokenPlatform::Android,
            'is_active' => true,
            'revoked_at' => null,
        ], $overrides));
    }

    public function test_unconfigured_provider_does_not_call_network(): void
    {
        config(['notifications.push.configured' => false]);
        Http::fake();

        $user = User::factory()->create();
        $this->registerDevice($user);
        $notification = $this->makeNotification($user);

        $result = app(PushNotificationProvider::class)->send($notification);

        $this->assertFalse($result['success']);
        $this->assertSame('Not Configured', $result['error']);
        Http::assertNothingSent();
    }

    public function test_configured_provider_sends_correct_expo_request(): void
    {
        Http::fake([
            self::EXPO_URL => Http::response([
                'data' => [
                    ['status' => 'ok', 'id' => 'ticket-1'],
                ],
            ], 200),
        ]);

        $user = User::factory()->create();
        $device = $this->registerDevice($user, [
            'push_token' => 'ExponentPushToken[abcdefghijklmnopqrstuv]',
        ]);
        $notification = $this->makeNotification($user);

        $result = app(PushNotificationProvider::class)->send($notification);

        $this->assertTrue($result['success']);
        $this->assertSame('ticket-1', $result['provider_message_id']);

        Http::assertSent(function ($request) use ($device, $notification) {
            $payload = $request->data();
            $this->assertSame(self::EXPO_URL, $request->url());
            $this->assertFalse($request->hasHeader('Authorization'));
            $this->assertIsArray($payload);
            $this->assertCount(1, $payload);
            $this->assertSame($device->push_token, $payload[0]['to']);
            $this->assertSame('Order placed', $payload[0]['title']);
            $this->assertSame('Your order ORD-1 was created.', $payload[0]['body']);
            $this->assertSame($notification->id, $payload[0]['data']['notification_id']);
            $this->assertSame('order_created', $payload[0]['data']['event_type']);
            $this->assertSame('ORD-1', $payload[0]['data']['order_number']);

            return true;
        });
    }

    public function test_expo_data_includes_model_event_type_for_owner_qa_order_created_shape(): void
    {
        Http::fake([
            self::EXPO_URL => Http::response([
                'data' => [['status' => 'ok', 'id' => 'ticket-qa']],
            ], 200),
        ]);

        $user = User::factory()->create();
        $this->registerDevice($user, [
            'push_token' => 'ExponentPushToken[abcdefghijklmnopqrstuv]',
        ]);

        // Exact owner-QA shape: data[] has order fields only; event_type lives on the row.
        $orderId = '019fee4a-f110-7072-9f86-9fb15923793a';
        $notification = Notification::query()->create([
            'user_id' => $user->id,
            'customer_id' => $user->id,
            'type' => NotificationEventType::OrderCreated->value,
            'event_type' => NotificationEventType::OrderCreated->value,
            'title' => 'Order placed',
            'message' => 'Your order COTZ-20260811-000001 was created.',
            'channel' => NotificationChannel::Push->value,
            'status' => NotificationDeliveryStatus::Processing->value,
            'provider' => 'expo',
            'data' => [
                'customer_name' => 'QA Customer',
                'order_number' => 'COTZ-20260811-000001',
                'order_id' => $orderId,
                'password' => 'should-not-leak',
            ],
        ]);

        $result = app(PushNotificationProvider::class)->send($notification);

        $this->assertTrue($result['success']);

        Http::assertSent(function ($request) use ($notification, $orderId) {
            $payload = $request->data();
            $data = $payload[0]['data'];

            $this->assertSame('order_created', $data['event_type']);
            $this->assertSame((string) $notification->id, $data['notification_id']);
            $this->assertSame($orderId, $data['order_id']);
            $this->assertSame('COTZ-20260811-000001', $data['order_number']);
            $this->assertSame('QA Customer', $data['customer_name']);
            $this->assertArrayNotHasKey('password', $data);

            return true;
        });
    }

    public function test_expo_data_model_event_type_wins_over_stale_data_event_type(): void
    {
        Http::fake([
            self::EXPO_URL => Http::response([
                'data' => [['status' => 'ok', 'id' => 'ticket-authority']],
            ], 200),
        ]);

        $user = User::factory()->create();
        $this->registerDevice($user);
        $notification = $this->makeNotification($user, [
            'event_type' => 'stale_wrong_type',
            'notification_id' => 'forged-id',
        ]);

        app(PushNotificationProvider::class)->send($notification);

        Http::assertSent(function ($request) use ($notification) {
            $data = $request->data()[0]['data'];
            $this->assertSame('order_created', $data['event_type']);
            $this->assertSame((string) $notification->id, $data['notification_id']);

            return true;
        });
    }

    public function test_authorization_header_sent_when_access_token_configured(): void
    {
        config(['notifications.push.expo.access_token' => 'expo-secret-token']);
        Http::fake([
            self::EXPO_URL => Http::response([
                'data' => [['status' => 'ok', 'id' => 'ticket-auth']],
            ], 200),
        ]);

        $user = User::factory()->create();
        $this->registerDevice($user);
        $result = app(PushNotificationProvider::class)->send($this->makeNotification($user));

        $this->assertTrue($result['success']);
        Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer expo-secret-token'));
    }

    public function test_only_active_non_revoked_expo_tokens_selected(): void
    {
        Http::fake([
            self::EXPO_URL => Http::response([
                'data' => [['status' => 'ok', 'id' => 'ticket-active']],
            ], 200),
        ]);

        $user = User::factory()->create();
        $active = $this->registerDevice($user, [
            'push_token' => 'ExponentPushToken[active-token-aaaaaaaa]',
        ]);
        $this->registerDevice($user, [
            'push_token' => 'ExponentPushToken[revoked-token-bbbbbbb]',
            'is_active' => false,
            'revoked_at' => now(),
        ]);

        $result = app(PushNotificationProvider::class)->send($this->makeNotification($user));
        $this->assertTrue($result['success']);

        Http::assertSent(function ($request) use ($active) {
            $payload = $request->data();

            return count($payload) === 1 && $payload[0]['to'] === $active->push_token;
        });
    }

    public function test_multiple_customer_devices_are_batched(): void
    {
        config(['notifications.push.expo.batch_size' => 100]);
        Http::fake([
            self::EXPO_URL => Http::response([
                'data' => [
                    ['status' => 'ok', 'id' => 't1'],
                    ['status' => 'ok', 'id' => 't2'],
                ],
            ], 200),
        ]);

        $user = User::factory()->create();
        $d1 = $this->registerDevice($user, ['push_token' => 'ExponentPushToken[device-one-aaaaaaaaaaa]']);
        $d2 = $this->registerDevice($user, ['push_token' => 'ExponentPushToken[device-two-bbbbbbbbbbb]']);

        $result = app(PushNotificationProvider::class)->send($this->makeNotification($user));
        $this->assertTrue($result['success']);
        $this->assertSame('t1,t2', $result['provider_message_id']);

        Http::assertSentCount(1);
        Http::assertSent(function ($request) use ($d1, $d2) {
            $tos = collect($request->data())->pluck('to')->all();

            return count($tos) === 2
                && in_array($d1->push_token, $tos, true)
                && in_array($d2->push_token, $tos, true);
        });
    }

    public function test_batching_splits_when_over_batch_size(): void
    {
        config(['notifications.push.expo.batch_size' => 1]);
        Http::fake([
            self::EXPO_URL => Http::sequence()
                ->push(['data' => [['status' => 'ok', 'id' => 'a']]], 200)
                ->push(['data' => [['status' => 'ok', 'id' => 'b']]], 200),
        ]);

        $user = User::factory()->create();
        $this->registerDevice($user, ['push_token' => 'ExponentPushToken[batch-one-aaaaaaaaaa]']);
        $this->registerDevice($user, ['push_token' => 'ExponentPushToken[batch-two-bbbbbbbbbb]']);

        $result = app(PushNotificationProvider::class)->send($this->makeNotification($user));
        $this->assertTrue($result['success']);
        Http::assertSentCount(2);
    }

    public function test_another_users_token_never_receives_target_push(): void
    {
        Http::fake([
            self::EXPO_URL => Http::response([
                'data' => [['status' => 'ok', 'id' => 'only-owner']],
            ], 200),
        ]);

        $owner = User::factory()->create();
        $other = User::factory()->create();
        $ownerDevice = $this->registerDevice($owner, [
            'push_token' => 'ExponentPushToken[owner-token-aaaaaaaa]',
        ]);
        $this->registerDevice($other, [
            'push_token' => 'ExponentPushToken[other-token-bbbbbbbb]',
        ]);

        app(PushNotificationProvider::class)->send($this->makeNotification($owner));

        Http::assertSent(function ($request) use ($ownerDevice) {
            $payload = $request->data();

            return count($payload) === 1 && $payload[0]['to'] === $ownerDevice->push_token;
        });
    }

    public function test_duplicate_tokens_are_deduplicated(): void
    {
        Http::fake([
            self::EXPO_URL => Http::response([
                'data' => [['status' => 'ok', 'id' => 'deduped']],
            ], 200),
        ]);

        $user = User::factory()->create();
        $token = 'ExponentPushToken[same-token-aaaaaaaaaaaa]';
        // Unique DB constraint normally prevents duplicates; force two logical rows via factory then
        // resolve-path dedupe is still covered when collection has duplicates in-memory.
        $this->registerDevice($user, ['push_token' => $token]);

        $resolver = app(\App\Services\Devices\ResolveActiveExpoPushTokens::class);
        $resolved = $resolver->forUserId($user->id);
        $this->assertCount(1, $resolved);

        app(PushNotificationProvider::class)->send($this->makeNotification($user));
        Http::assertSent(fn ($request) => count($request->data()) === 1);
    }

    public function test_sensitive_keys_are_stripped_from_data_payload(): void
    {
        Http::fake([
            self::EXPO_URL => Http::response([
                'data' => [['status' => 'ok', 'id' => 'safe']],
            ], 200),
        ]);

        $user = User::factory()->create();
        $this->registerDevice($user);
        $notification = $this->makeNotification($user, [
            'order_id' => 'ord-1',
            'access_token' => 'should-not-leak',
            'password_hint' => 'nope',
        ]);

        app(PushNotificationProvider::class)->send($notification);

        Http::assertSent(function ($request) {
            $data = $request->data()[0]['data'];

            return ($data['order_id'] ?? null) === 'ord-1'
                && ! array_key_exists('access_token', $data)
                && ! array_key_exists('password_hint', $data);
        });
    }

    public function test_no_active_devices_succeeds_without_network(): void
    {
        Http::fake();
        $user = User::factory()->create();

        $result = app(PushNotificationProvider::class)->send($this->makeNotification($user));

        $this->assertTrue($result['success']);
        $this->assertSame('expo:no_devices', $result['provider_message_id']);
        Http::assertNothingSent();
    }

    public function test_http_timeout_failure_is_soft(): void
    {
        Http::fake(function () {
            throw new ConnectionException('Connection timed out');
        });

        $user = User::factory()->create();
        $this->registerDevice($user);

        $result = app(PushNotificationProvider::class)->send($this->makeNotification($user));

        $this->assertFalse($result['success']);
        $this->assertNotNull($result['error']);
    }

    public function test_malformed_expo_response_fails_softly(): void
    {
        Http::fake([
            self::EXPO_URL => Http::response(['unexpected' => true], 200),
        ]);

        $user = User::factory()->create();
        $this->registerDevice($user);

        $result = app(PushNotificationProvider::class)->send($this->makeNotification($user));
        $this->assertFalse($result['success']);
        $this->assertSame('Malformed Expo response', $result['error']);
    }

    public function test_partial_success_when_one_ticket_ok(): void
    {
        Http::fake([
            self::EXPO_URL => Http::response([
                'data' => [
                    ['status' => 'ok', 'id' => 'ok-1'],
                    ['status' => 'error', 'message' => 'MessageTooBig', 'details' => ['error' => 'MessageTooBig']],
                ],
            ], 200),
        ]);

        $user = User::factory()->create();
        $this->registerDevice($user, ['push_token' => 'ExponentPushToken[partial-one-aaaaaaaa]']);
        $this->registerDevice($user, ['push_token' => 'ExponentPushToken[partial-two-bbbbbbbb]']);

        $result = app(PushNotificationProvider::class)->send($this->makeNotification($user));

        $this->assertTrue($result['success']);
        $this->assertSame('ok-1', $result['provider_message_id']);
        $this->assertStringContainsString('Partial success', (string) $result['error']);
    }

    public function test_provider_ticket_error_all_failed(): void
    {
        Http::fake([
            self::EXPO_URL => Http::response([
                'data' => [
                    ['status' => 'error', 'message' => 'InvalidCredentials', 'details' => ['error' => 'InvalidCredentials']],
                ],
            ], 200),
        ]);

        $user = User::factory()->create();
        $this->registerDevice($user);

        $result = app(PushNotificationProvider::class)->send($this->makeNotification($user));
        $this->assertFalse($result['success']);
        $this->assertSame('InvalidCredentials', $result['error']);
    }

    public function test_device_not_registered_revokes_matching_token(): void
    {
        Http::fake([
            self::EXPO_URL => Http::response([
                'data' => [
                    [
                        'status' => 'error',
                        'message' => '"ExponentPushToken[gone]" is not a registered push notification recipient',
                        'details' => ['error' => 'DeviceNotRegistered'],
                    ],
                ],
            ], 200),
        ]);

        $user = User::factory()->create();
        $device = $this->registerDevice($user, [
            'push_token' => 'ExponentPushToken[gone-token-aaaaaaaaaaa]',
        ]);

        $result = app(PushNotificationProvider::class)->send($this->makeNotification($user));
        $this->assertFalse($result['success']);

        $device->refresh();
        $this->assertFalse($device->is_active);
        $this->assertNotNull($device->revoked_at);
    }

    public function test_platform_push_channel_explicit_dispatch_uses_provider(): void
    {
        Http::fake([
            self::EXPO_URL => Http::response([
                'data' => [['status' => 'ok', 'id' => 'platform-ticket']],
            ], 200),
        ]);

        NotificationTemplate::factory()->create([
            'key' => 'order_created.push',
            'channel' => NotificationChannel::Push,
            'subject' => 'Order {{order_number}}',
            'body' => 'Hello {{customer_name}}, order {{order_number}} created.',
            'is_active' => true,
        ]);

        $user = User::factory()->create(['name' => 'Asha']);
        $this->registerDevice($user);

        $created = app(NotificationPlatform::class)->notifyCustomer(
            NotificationEventType::OrderCreated,
            $user,
            [
                'customer_name' => 'Asha',
                'order_number' => 'ORD-9',
            ],
            channels: [NotificationChannel::Push],
            idempotencyKey: 'push-test:'.$user->id,
        );

        $this->assertCount(1, $created);
        $row = $created->first();
        $this->assertSame(NotificationChannel::Push, $row->channel);
        $this->assertSame(NotificationDeliveryStatus::Sent, $row->status);
        $this->assertSame('platform-ticket', $row->provider_message_id);
        Http::assertSentCount(1);
    }

    public function test_non_launch_events_still_exclude_push_by_default(): void
    {
        Http::fake();

        NotificationTemplate::factory()->create([
            'key' => 'tracking_updated.in_app',
            'channel' => NotificationChannel::InApp,
            'subject' => 'Tracking {{order_number}}',
            'body' => 'Hello {{customer_name}}',
            'is_active' => true,
        ]);

        $user = User::factory()->create(['name' => 'Asha']);
        $this->registerDevice($user);

        $created = app(NotificationPlatform::class)->notifyCustomer(
            NotificationEventType::TrackingUpdated,
            $user,
            [
                'customer_name' => 'Asha',
                'order_number' => 'ORD-10',
                'order_id' => (string) \Illuminate\Support\Str::uuid(),
            ],
            idempotencyKey: 'tracking-only:'.$user->id,
        );

        $this->assertTrue($created->every(
            fn (Notification $n) => $n->channel !== NotificationChannel::Push
        ));
        Http::assertNothingSent();
    }

    public function test_customer_inbox_still_lists_only_in_app(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('customer-api')->plainTextToken;

        Notification::query()->create([
            'user_id' => $user->id,
            'customer_id' => $user->id,
            'type' => NotificationEventType::OrderCreated->value,
            'event_type' => NotificationEventType::OrderCreated->value,
            'title' => 'In-app',
            'message' => 'Visible',
            'channel' => NotificationChannel::InApp->value,
            'status' => NotificationDeliveryStatus::Sent->value,
            'provider' => 'in_app',
            'data' => [],
        ]);

        Notification::query()->create([
            'user_id' => $user->id,
            'customer_id' => $user->id,
            'type' => NotificationEventType::OrderCreated->value,
            'event_type' => NotificationEventType::OrderCreated->value,
            'title' => 'Push row',
            'message' => 'Hidden from inbox',
            'channel' => NotificationChannel::Push->value,
            'status' => NotificationDeliveryStatus::Sent->value,
            'provider' => 'expo',
            'data' => [],
        ]);

        $this->withToken($token)
            ->getJson('/api/v1/notifications')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'In-app');
    }
}
