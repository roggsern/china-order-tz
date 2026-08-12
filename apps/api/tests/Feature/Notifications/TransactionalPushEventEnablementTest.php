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
use App\Services\Notifications\NotificationConfigurationResolver;
use App\Services\Notifications\NotificationPlatform;
use Database\Seeders\NotificationTemplateSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class TransactionalPushEventEnablementTest extends TestCase
{
    use RefreshDatabase;

    private const EXPO_URL = 'https://exp.host/--/api/v2/push/send';

    /** @var list<NotificationEventType> */
    private const LAUNCH_PUSH_EVENTS = [
        NotificationEventType::OrderCreated,
        NotificationEventType::OrderCancelled,
        NotificationEventType::PaymentConfirmed,
        NotificationEventType::ShipmentCreated,
        NotificationEventType::ShipmentArrivedTanzania,
        NotificationEventType::OrderDelivered,
        NotificationEventType::SupportReplyReceived,
        NotificationEventType::PasswordChanged,
        NotificationEventType::EmailChanged,
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SettingsSeeder::class);
        $this->seed(NotificationTemplateSeeder::class);

        config([
            'notifications.push.configured' => true,
            'notifications.push.driver' => 'expo',
            'notifications.push.expo.url' => self::EXPO_URL,
            'notifications.push.expo.access_token' => null,
            'notifications.push.expo.timeout' => 3,
            'notifications.push.expo.connect_timeout' => 2,
            'notifications.push.expo.batch_size' => 100,
            'notifications.email.configured' => false,
            'notifications.whatsapp.configured' => false,
            'notifications.sms.configured' => false,
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

    public function test_launch_tier_a_events_resolve_push_channel(): void
    {
        $resolver = app(NotificationConfigurationResolver::class);

        foreach (self::LAUNCH_PUSH_EVENTS as $event) {
            $channels = $resolver->resolveEventChannels($event);
            $values = array_map(
                static fn (NotificationChannel $c): string => $c->value,
                $channels,
            );
            $this->assertContains(
                NotificationChannel::Push->value,
                $values,
                "Expected push for {$event->value}",
            );
            $this->assertContains(NotificationChannel::InApp->value, $values);
        }
    }

    public function test_non_selected_and_marketing_events_do_not_resolve_push(): void
    {
        $resolver = app(NotificationConfigurationResolver::class);

        foreach ([
            NotificationEventType::TrackingUpdated,
            NotificationEventType::WarehousePacked,
            NotificationEventType::ReviewApproved,
            NotificationEventType::GrowthCampaign,
            NotificationEventType::PasswordReset,
            NotificationEventType::EmailChangeRequested,
            NotificationEventType::OtpRequested,
            NotificationEventType::SupportTicketAssigned,
        ] as $event) {
            $values = array_map(
                static fn (NotificationChannel $c): string => $c->value,
                $resolver->resolveEventChannels($event),
            );
            $this->assertNotContains(
                NotificationChannel::Push->value,
                $values,
                "Did not expect push for {$event->value}",
            );
        }
    }

    public function test_existing_in_app_email_whatsapp_channel_lists_preserved_with_push_appended(): void
    {
        $this->assertSame(
            ['in_app', 'whatsapp', 'email', 'push'],
            config('notifications.event_channels.order_created'),
        );
        $this->assertSame(
            ['in_app', 'whatsapp', 'email', 'push'],
            config('notifications.event_channels.payment_confirmed'),
        );
        $this->assertSame(
            ['in_app', 'push'],
            config('notifications.event_channels.order_cancelled'),
        );
        $this->assertSame(
            ['in_app'],
            config('notifications.event_channels.tracking_updated'),
        );
        $this->assertSame(
            ['in_app', 'push'],
            config('notifications.event_channels.support_reply_received'),
        );
    }

    public function test_push_unconfigured_soft_fails_without_breaking_in_app(): void
    {
        config(['notifications.push.configured' => false]);
        Http::fake();

        $user = User::factory()->create(['name' => 'Asha']);
        $this->registerDevice($user);

        $created = app(NotificationPlatform::class)->notifyCustomer(
            NotificationEventType::OrderCancelled,
            $user,
            [
                'customer_name' => 'Asha',
                'order_number' => 'ORD-1',
                'order_id' => (string) Str::uuid(),
            ],
            idempotencyKey: 'cancel-unconfigured:'.$user->id,
        );

        $this->assertTrue($created->contains(
            fn (Notification $n) => $n->channel === NotificationChannel::InApp
                && $n->status === NotificationDeliveryStatus::Sent,
        ));
        $this->assertFalse($created->contains(
            fn (Notification $n) => $n->channel === NotificationChannel::Push,
        ));
        Http::assertNothingSent();
    }

    public function test_no_device_customer_still_gets_in_app_and_push_row_soft_succeeds(): void
    {
        Http::fake();
        $user = User::factory()->create(['name' => 'Asha']);

        $created = app(NotificationPlatform::class)->notifyCustomer(
            NotificationEventType::OrderCancelled,
            $user,
            [
                'customer_name' => 'Asha',
                'order_number' => 'ORD-2',
                'order_id' => (string) Str::uuid(),
            ],
            idempotencyKey: 'cancel-nodevice:'.$user->id,
        );

        $push = $created->first(
            fn (Notification $n) => $n->channel === NotificationChannel::Push,
        );
        $this->assertNotNull($push);
        $this->assertSame(NotificationDeliveryStatus::Sent, $push->status);
        $this->assertSame('expo:no_devices', $push->provider_message_id);
        Http::assertNothingSent();
    }

    public function test_multi_device_push_and_revoked_excluded(): void
    {
        Http::fake([
            self::EXPO_URL => Http::response([
                'data' => [
                    ['status' => 'ok', 'id' => 'a'],
                    ['status' => 'ok', 'id' => 'b'],
                ],
            ], 200),
        ]);

        $user = User::factory()->create(['name' => 'Asha']);
        $activeA = $this->registerDevice($user, [
            'push_token' => 'ExponentPushToken[launch-device-aaaaaaaa]',
        ]);
        $activeB = $this->registerDevice($user, [
            'push_token' => 'ExponentPushToken[launch-device-bbbbbbbb]',
        ]);
        $this->registerDevice($user, [
            'push_token' => 'ExponentPushToken[launch-device-revokedxx]',
            'is_active' => false,
            'revoked_at' => now(),
        ]);

        $other = User::factory()->create();
        $this->registerDevice($other, [
            'push_token' => 'ExponentPushToken[other-user-tokenxxxxxx]',
        ]);

        app(NotificationPlatform::class)->notifyCustomer(
            NotificationEventType::ShipmentCreated,
            $user,
            [
                'customer_name' => 'Asha',
                'order_number' => 'ORD-3',
                'order_id' => (string) Str::uuid(),
                'shipment_id' => (string) Str::uuid(),
            ],
            idempotencyKey: 'ship-multi:'.$user->id,
        );

        Http::assertSent(function ($request) use ($activeA, $activeB) {
            $tos = collect($request->data())->pluck('to')->all();

            return count($tos) === 2
                && in_array($activeA->push_token, $tos, true)
                && in_array($activeB->push_token, $tos, true)
                && ! in_array('ExponentPushToken[other-user-tokenxxxxxx]', $tos, true)
                && ! in_array('ExponentPushToken[launch-device-revokedxx]', $tos, true);
        });
    }

    public function test_push_copy_avoids_sensitive_amounts_and_preserves_semantic_refs(): void
    {
        Http::fake([
            self::EXPO_URL => Http::response([
                'data' => [['status' => 'ok', 'id' => 'pay-1']],
            ], 200),
        ]);

        $user = User::factory()->create(['name' => 'Asha']);
        $this->registerDevice($user);
        $orderId = (string) Str::uuid();

        app(NotificationPlatform::class)->notifyCustomer(
            NotificationEventType::PaymentConfirmed,
            $user,
            [
                'customer_name' => 'Asha',
                'order_number' => 'ORD-PAY',
                'order_id' => $orderId,
                'order_total' => '1587000',
                'currency' => 'TZS',
                'access_token' => 'should-not-appear',
            ],
            idempotencyKey: 'pay-copy:'.$user->id,
        );

        Http::assertSent(function ($request) use ($orderId) {
            $message = $request->data()[0];
            $title = (string) $message['title'];
            $body = (string) $message['body'];
            $data = $message['data'];

            $this->assertStringNotContainsString('1587000', $title.$body);
            $this->assertStringNotContainsString('TZS', $title.$body);
            $this->assertSame($orderId, $data['order_id']);
            $this->assertSame('ORD-PAY', $data['order_number']);
            $this->assertSame('payment_confirmed', $data['event_type']);
            $this->assertArrayNotHasKey('access_token', $data);

            return true;
        });
    }

    public function test_idempotent_domain_event_does_not_duplicate_channel_rows(): void
    {
        Http::fake([
            self::EXPO_URL => Http::response([
                'data' => [['status' => 'ok', 'id' => 'idem-1']],
            ], 200),
        ]);

        $user = User::factory()->create(['name' => 'Asha']);
        $this->registerDevice($user);
        $key = 'order-cancelled:idem-'.$user->id;

        $first = app(NotificationPlatform::class)->notifyCustomer(
            NotificationEventType::OrderCancelled,
            $user,
            [
                'customer_name' => 'Asha',
                'order_number' => 'ORD-ID',
                'order_id' => (string) Str::uuid(),
            ],
            idempotencyKey: $key,
        );
        $second = app(NotificationPlatform::class)->notifyCustomer(
            NotificationEventType::OrderCancelled,
            $user,
            [
                'customer_name' => 'Asha',
                'order_number' => 'ORD-ID',
                'order_id' => (string) Str::uuid(),
            ],
            idempotencyKey: $key,
        );

        $this->assertSame($first->pluck('id')->sort()->values()->all(), $second->pluck('id')->sort()->values()->all());
        $this->assertSame(
            1,
            Notification::query()->where('correlation_key', $key)->where('channel', 'push')->count(),
        );
        Http::assertSentCount(1);
    }

    public function test_expo_http_failure_does_not_break_in_app_channel(): void
    {
        Http::fake([
            self::EXPO_URL => Http::response(['error' => 'down'], 500),
        ]);

        $user = User::factory()->create(['name' => 'Asha']);
        $this->registerDevice($user);

        $created = app(NotificationPlatform::class)->notifyCustomer(
            NotificationEventType::OrderCancelled,
            $user,
            [
                'customer_name' => 'Asha',
                'order_number' => 'ORD-FAIL',
                'order_id' => (string) Str::uuid(),
            ],
            idempotencyKey: 'cancel-http-fail:'.$user->id,
        );

        $inApp = $created->first(fn (Notification $n) => $n->channel === NotificationChannel::InApp);
        $push = $created->first(fn (Notification $n) => $n->channel === NotificationChannel::Push);

        $this->assertNotNull($inApp);
        $this->assertSame(NotificationDeliveryStatus::Sent, $inApp->status);
        $this->assertNotNull($push);
        $this->assertSame(NotificationDeliveryStatus::Failed, $push->status);
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
            'title' => 'Push',
            'message' => 'Hidden',
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

    public function test_push_templates_seeded_for_launch_events(): void
    {
        foreach (self::LAUNCH_PUSH_EVENTS as $event) {
            $this->assertTrue(
                NotificationTemplate::query()
                    ->where('key', $event->defaultTemplateKey(NotificationChannel::Push))
                    ->where('channel', NotificationChannel::Push->value)
                    ->where('is_active', true)
                    ->exists(),
                "Missing push template for {$event->value}",
            );
        }
    }
}
