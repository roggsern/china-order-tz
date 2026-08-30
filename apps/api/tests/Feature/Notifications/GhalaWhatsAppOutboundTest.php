<?php

namespace Tests\Feature\Notifications;

use App\Enums\NotificationChannel;
use App\Enums\NotificationDeliveryStatus;
use App\Enums\NotificationEventType;
use App\Models\Notification;
use App\Models\Order;
use App\Models\ShippingAddress;
use App\Models\User;
use App\Services\Notifications\NotificationPlatform;
use App\Services\Notifications\Providers\WhatsAppNotificationProvider;
use App\Services\Notifications\WhatsApp\GhalaWhatsAppTemplateMapper;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GhalaWhatsAppOutboundTest extends TestCase
{
    use RefreshDatabase;

    private const TOKEN = 'test-ghala-access-token-secret-value';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SettingsSeeder::class);
    }

    public function test_provider_unconfigured(): void
    {
        config([
            'notifications.whatsapp.configured' => false,
            'notifications.whatsapp.access_token' => self::TOKEN,
            'notifications.whatsapp.base_url' => 'https://v2.ghala.io',
        ]);

        $result = app(WhatsAppNotificationProvider::class)->send($this->makeWhatsAppNotification());

        $this->assertFalse($result['success']);
        $this->assertSame('Not Configured', $result['error']);
        Http::assertNothingSent();
    }

    public function test_legacy_meta_driver_is_not_configured_and_does_not_send(): void
    {
        $this->configureGhala();
        config(['notifications.whatsapp.driver' => 'meta_cloud']);
        Http::fake();

        $result = app(WhatsAppNotificationProvider::class)->send($this->makeWhatsAppNotification());

        $this->assertFalse($result['success']);
        $this->assertSame('Not Configured', $result['error']);
        Http::assertNothingSent();
    }

    public function test_customer_phone_missing_and_invalid(): void
    {
        $this->configureGhala();

        $missing = User::factory()->create(['phone' => null]);
        $this->assertSame(
            'Customer phone missing',
            app(WhatsAppNotificationProvider::class)->send($this->makeWhatsAppNotification($missing))['error'],
        );

        $invalid = User::factory()->create(['phone' => 'abc']);
        $this->assertSame(
            'Invalid WhatsApp destination phone',
            app(WhatsAppNotificationProvider::class)->send($this->makeWhatsAppNotification($invalid))['error'],
        );
        Http::assertNothingSent();
    }

    public function test_uses_account_phone_not_shipping_or_payment_phone(): void
    {
        $this->configureGhala();
        Http::fake([
            'https://v2.ghala.io/api/v2/messages' => Http::response([
                'id' => '01ACCOUNTPHONE',
                'status' => 'sent',
            ], 200),
        ]);

        $user = User::factory()->create(['phone' => '+255712345678', 'name' => 'Asha']);
        $order = Order::factory()->create(['user_id' => $user->id]);
        ShippingAddress::factory()->create([
            'user_id' => $user->id,
            'order_id' => $order->id,
            'phone' => '+255700000099',
        ]);

        app(NotificationPlatform::class)->notifyCustomer(
            NotificationEventType::OrderCreated,
            $user,
            [
                'customer_name' => 'Asha',
                'order_number' => $order->order_number,
                'order_total' => '50000',
                'currency' => 'TZS',
                'order_id' => $order->id,
                'payment_phone' => '255700000011',
                'shipping_phone' => '+255700000099',
            ],
            channels: [NotificationChannel::WhatsApp],
            idempotencyKey: 'order_created:'.$order->id.':'.$user->id,
        );

        Http::assertSent(function ($request) {
            return ($request->data()['to'] ?? null) === '255712345678';
        });
    }

    public function test_successful_send_persists_ghala_ids_and_en_us_template(): void
    {
        $this->configureGhala();
        Http::fake([
            'https://v2.ghala.io/api/v2/messages' => Http::response([
                'id' => '01GHALASUCCESS',
                'status' => 'sent',
                'wa_message_id' => 'wamid.OK',
            ], 200),
        ]);

        $user = User::factory()->create(['phone' => '0712345678', 'name' => 'Asha']);
        $key = 'order_created:test-success:'.$user->id;
        $created = app(NotificationPlatform::class)->notifyCustomer(
            NotificationEventType::OrderCreated,
            $user,
            [
                'customer_name' => 'Asha',
                'order_number' => 'ORD-100',
                'order_total' => '50000',
                'currency' => 'TZS',
            ],
            channels: [NotificationChannel::WhatsApp],
            idempotencyKey: $key,
        );

        $notification = $created->first();
        $this->assertSame(NotificationDeliveryStatus::Sent, $notification->status);
        $this->assertSame('01GHALASUCCESS', $notification->provider_message_id);
        $this->assertSame('ghala', $notification->provider);
        $this->assertSame('order_confirmation', $notification->data['whatsapp_template'] ?? null);
        $this->assertSame('en_US', $notification->data['whatsapp_language'] ?? null);
        $this->assertSame('wamid.OK', $notification->data['whatsapp_wa_message_id'] ?? null);

        Http::assertSent(function ($request) use ($key) {
            $body = $request->data();

            return $request->url() === 'https://v2.ghala.io/api/v2/messages'
                && ($body['to'] ?? null) === '255712345678'
                && ($body['template_name'] ?? null) === 'order_confirmation'
                && ($body['template_language'] ?? null) === 'en_US'
                && ($body['template_components'][0]['parameters'][2]['text'] ?? null) === '50000.00 TZS'
                && ($request->header('Idempotency-Key')[0] ?? null) === $key.':whatsapp';
        });
    }

    public function test_in_app_still_succeeds_when_ghala_fails(): void
    {
        $this->configureGhala();
        Http::fake([
            'https://v2.ghala.io/api/v2/messages' => Http::response(['message' => 'fail'], 500),
        ]);

        $user = User::factory()->create(['phone' => '+255712345678']);
        $created = app(NotificationPlatform::class)->notifyCustomer(
            NotificationEventType::OrderCreated,
            $user,
            [
                'customer_name' => 'Asha',
                'order_number' => 'ORD-500',
                'order_total' => '10',
                'currency' => 'TZS',
            ],
            channels: [NotificationChannel::InApp, NotificationChannel::WhatsApp],
            idempotencyKey: 'order_created:dual:'.$user->id,
        );

        $inApp = $created->first(fn (Notification $n) => $n->channel === NotificationChannel::InApp);
        $whatsapp = $created->first(fn (Notification $n) => $n->channel === NotificationChannel::WhatsApp);

        $this->assertSame(NotificationDeliveryStatus::Sent, $inApp?->status);
        $this->assertSame(NotificationDeliveryStatus::Failed, $whatsapp?->status);
    }

    public function test_idempotency_prevents_duplicate_send(): void
    {
        $this->configureGhala();
        Http::fake([
            'https://v2.ghala.io/api/v2/messages' => Http::response([
                'id' => '01ONCE',
                'status' => 'sent',
            ], 200),
        ]);

        $user = User::factory()->create(['phone' => '+255712345678']);
        $key = 'payment_confirmed:order-dup:'.$user->id;

        $first = app(NotificationPlatform::class)->notifyCustomer(
            NotificationEventType::PaymentConfirmed,
            $user,
            ['customer_name' => 'Asha', 'order_number' => 'ORD-DUP', 'order_total' => '1', 'currency' => 'TZS'],
            channels: [NotificationChannel::WhatsApp],
            idempotencyKey: $key,
            correlationKey: $key,
        );
        $second = app(NotificationPlatform::class)->notifyCustomer(
            NotificationEventType::PaymentConfirmed,
            $user,
            ['customer_name' => 'Asha', 'order_number' => 'ORD-DUP', 'order_total' => '1', 'currency' => 'TZS'],
            channels: [NotificationChannel::WhatsApp],
            idempotencyKey: $key,
            correlationKey: $key,
        );

        $this->assertSame($first->first()->id, $second->first()->id);
        Http::assertSentCount(1);
    }

    public function test_unmapped_events_stay_off_whatsapp(): void
    {
        $this->assertSame(
            ['in_app'],
            config('notifications.event_channels.tracking_updated'),
        );

        $this->configureGhala();
        Http::fake();

        $user = User::factory()->create(['phone' => '+255712345678']);
        $created = app(NotificationPlatform::class)->notifyCustomer(
            NotificationEventType::TrackingUpdated,
            $user,
            ['customer_name' => 'Asha', 'order_number' => 'ORD-X'],
        );

        $this->assertTrue($created->every(fn (Notification $n) => $n->channel === NotificationChannel::InApp));
        Http::assertNothingSent();
    }

    public function test_eight_supported_events_are_mapped(): void
    {
        $this->assertSame([
            NotificationEventType::OrderCreated->value,
            NotificationEventType::PaymentConfirmed->value,
            NotificationEventType::OrderProcessing->value,
            NotificationEventType::ShipmentArrivedTanzania->value,
            NotificationEventType::WarehouseReadyForPickup->value,
            NotificationEventType::ShipmentCreated->value,
            NotificationEventType::OrderDelivered->value,
            NotificationEventType::OrderCancelled->value,
        ], GhalaWhatsAppTemplateMapper::supportedEventTypes());
    }

    private function configureGhala(): void
    {
        config([
            'notifications.whatsapp.configured' => true,
            'notifications.whatsapp.driver' => 'ghala',
            'notifications.whatsapp.access_token' => self::TOKEN,
            'notifications.whatsapp.base_url' => 'https://v2.ghala.io',
            'notifications.whatsapp.default_language' => 'en_US',
            'notifications.whatsapp.retry_attempts' => 3,
            'notifications.whatsapp.retry_sleep_ms' => 0,
        ]);
    }

    private function makeWhatsAppNotification(?User $user = null): Notification
    {
        $user ??= User::factory()->create(['phone' => '+255712345678']);

        return Notification::factory()->create([
            'user_id' => $user->id,
            'customer_id' => $user->id,
            'channel' => NotificationChannel::WhatsApp,
            'event_type' => NotificationEventType::OrderCreated->value,
            'type' => NotificationEventType::OrderCreated->value,
            'data' => [
                'customer_name' => 'Asha',
                'order_number' => 'ORD-1',
                'order_total' => '1000',
                'currency' => 'TZS',
            ],
        ]);
    }
}
