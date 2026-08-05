<?php

namespace Tests\Feature\Notifications;

use App\Enums\NotificationChannel;
use App\Enums\NotificationDeliveryStatus;
use App\Enums\NotificationEventType;
use App\Models\Notification;
use App\Models\Order;
use App\Models\User;
use App\Services\Notifications\NotificationPlatform;
use App\Services\Notifications\Providers\WhatsAppNotificationProvider;
use App\Services\Notifications\WhatsApp\MetaWhatsAppTemplateMapper;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class WhatsAppOutboundMvpTest extends TestCase
{
    use RefreshDatabase;

    private const TOKEN = 'test-whatsapp-access-token-secret-value';

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
            'notifications.whatsapp.phone_number_id' => '123',
        ]);

        $notification = $this->makeWhatsAppNotification();
        $result = app(WhatsAppNotificationProvider::class)->send($notification);

        $this->assertFalse($result['success']);
        $this->assertSame('Not Configured', $result['error']);
        Http::assertNothingSent();
    }

    public function test_customer_missing(): void
    {
        $this->configureWhatsApp();
        $notification = Notification::factory()->create([
            'user_id' => null,
            'customer_id' => null,
            'channel' => NotificationChannel::WhatsApp,
            'event_type' => NotificationEventType::OrderCreated->value,
            'data' => [
                'customer_name' => 'Asha',
                'order_number' => 'ORD-1',
                'order_total' => '1000',
                'currency' => 'TZS',
            ],
        ]);

        $result = app(WhatsAppNotificationProvider::class)->send($notification);

        $this->assertFalse($result['success']);
        $this->assertSame('Customer missing', $result['error']);
        Http::assertNothingSent();
    }

    public function test_customer_phone_missing(): void
    {
        $this->configureWhatsApp();
        $user = User::factory()->create(['phone' => null]);
        $notification = $this->makeWhatsAppNotification($user);

        $result = app(WhatsAppNotificationProvider::class)->send($notification);

        $this->assertFalse($result['success']);
        $this->assertSame('Customer phone missing', $result['error']);
        Http::assertNothingSent();
    }

    public function test_invalid_e164_phone(): void
    {
        $this->configureWhatsApp();
        $user = User::factory()->create(['phone' => '0712345678']);
        $notification = $this->makeWhatsAppNotification($user);

        $result = app(WhatsAppNotificationProvider::class)->send($notification);

        $this->assertFalse($result['success']);
        $this->assertSame('Invalid E.164 phone', $result['error']);
        Http::assertNothingSent();
    }

    public function test_meta_mocked_success_persists_provider_message_id_and_snapshot(): void
    {
        $this->configureWhatsApp();
        Http::fake([
            'graph.facebook.com/*' => Http::response([
                'messages' => [['id' => 'wamid.TEST_MESSAGE_001']],
            ], 200),
        ]);

        $user = User::factory()->create(['phone' => '+255712345678', 'name' => 'Asha']);
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
            idempotencyKey: 'order_created:test-success:'.$user->id,
        );

        $notification = $created->first();
        $this->assertNotNull($notification);
        $this->assertSame(NotificationDeliveryStatus::Sent, $notification->status);
        $this->assertSame('wamid.TEST_MESSAGE_001', $notification->provider_message_id);
        $this->assertSame('+********5678', $notification->data['whatsapp_recipient_masked'] ?? null);
        $this->assertSame('order_created', $notification->data['whatsapp_template'] ?? null);
        $this->assertSame('en', $notification->data['whatsapp_language'] ?? null);

        Http::assertSent(function ($request) {
            $body = $request->data();

            return $request->url() === 'https://graph.facebook.com/v21.0/phone-number-id-test/messages'
                && ($body['messaging_product'] ?? null) === 'whatsapp'
                && ($body['to'] ?? null) === '255712345678'
                && ($body['type'] ?? null) === 'template'
                && ($body['template']['name'] ?? null) === 'order_created'
                && ($body['template']['language']['code'] ?? null) === 'en'
                && ($body['template']['components'][0]['parameters'][0]['text'] ?? null) === 'Asha'
                && ($body['template']['components'][0]['parameters'][1]['text'] ?? null) === 'ORD-100'
                && ($body['template']['components'][0]['parameters'][2]['text'] ?? null) === '50000'
                && ($body['template']['components'][0]['parameters'][3]['text'] ?? null) === 'TZS'
                && ! str_contains(json_encode($body) ?: '', self::TOKEN);
        });
    }

    public function test_meta_mocked_400_persists_safe_error(): void
    {
        $this->configureWhatsApp();
        Http::fake([
            'graph.facebook.com/*' => Http::response([
                'error' => [
                    'message' => 'Invalid parameter',
                    'code' => 100,
                ],
            ], 400),
        ]);

        $user = User::factory()->create(['phone' => '+255712345678']);
        $created = app(NotificationPlatform::class)->notifyCustomer(
            NotificationEventType::PaymentConfirmed,
            $user,
            [
                'customer_name' => 'Asha',
                'order_number' => 'ORD-200',
                'order_total' => '1000',
                'currency' => 'TZS',
            ],
            channels: [NotificationChannel::WhatsApp],
        );

        $notification = $created->first();
        $this->assertSame(NotificationDeliveryStatus::Failed, $notification->status);
        $this->assertStringContainsString('Meta HTTP 400', (string) $notification->error_message);
        $this->assertStringContainsString('Invalid parameter', (string) $notification->error_message);
        $this->assertStringNotContainsString(self::TOKEN, (string) $notification->error_message);
    }

    public function test_meta_mocked_500_persists_failed_status(): void
    {
        $this->configureWhatsApp();
        Http::fake([
            'graph.facebook.com/*' => Http::response([
                'error' => ['message' => 'Service unavailable', 'code' => 2],
            ], 500),
        ]);

        $user = User::factory()->create(['phone' => '+255700000001']);
        $created = app(NotificationPlatform::class)->notifyCustomer(
            NotificationEventType::ShipmentArrivedTanzania,
            $user,
            [
                'customer_name' => 'Asha',
                'order_number' => 'ORD-300',
                'location' => 'Dar es Salaam',
            ],
            channels: [NotificationChannel::WhatsApp],
        );

        $this->assertSame(NotificationDeliveryStatus::Failed, $created->first()->status);
        $this->assertStringContainsString('Meta HTTP 500', (string) $created->first()->error_message);
    }

    public function test_network_timeout_exception_is_caught(): void
    {
        $this->configureWhatsApp();
        Http::fake(function () {
            throw new ConnectionException('cURL error 28: Operation timed out for token '.self::TOKEN);
        });

        Log::spy();

        $user = User::factory()->create(['phone' => '+255712345678']);
        $created = app(NotificationPlatform::class)->notifyCustomer(
            NotificationEventType::OrderDelivered,
            $user,
            [
                'customer_name' => 'Asha',
                'order_number' => 'ORD-400',
            ],
            channels: [NotificationChannel::WhatsApp],
        );

        $notification = $created->first();
        $this->assertSame(NotificationDeliveryStatus::Failed, $notification->status);
        $this->assertStringContainsString('Meta connection/timeout', (string) $notification->error_message);
        $this->assertStringNotContainsString(self::TOKEN, (string) $notification->error_message);
        $this->assertStringContainsString('[redacted]', (string) $notification->error_message);
    }

    public function test_in_app_still_succeeds_when_whatsapp_fails(): void
    {
        $this->configureWhatsApp();
        Http::fake([
            'graph.facebook.com/*' => Http::response(['error' => ['message' => 'fail']], 500),
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

        $this->assertCount(2, $created);
        $inApp = $created->first(fn (Notification $n) => $n->channel === NotificationChannel::InApp);
        $whatsapp = $created->first(fn (Notification $n) => $n->channel === NotificationChannel::WhatsApp);

        $this->assertNotNull($inApp);
        $this->assertNotNull($whatsapp);
        $this->assertSame(NotificationDeliveryStatus::Sent, $inApp->status);
        $this->assertSame(NotificationDeliveryStatus::Failed, $whatsapp->status);
    }

    public function test_order_notify_failure_does_not_throw_or_delete_order(): void
    {
        $this->configureWhatsApp();
        Http::fake([
            'graph.facebook.com/*' => Http::response(['error' => ['message' => 'down']], 503),
        ]);

        $user = User::factory()->create(['phone' => '+255712345678']);
        $order = Order::factory()->create(['user_id' => $user->id]);

        $thrown = null;
        try {
            $key = 'order_created:'.$order->id.':'.$user->id;
            app(NotificationPlatform::class)->notifyCustomer(
                NotificationEventType::OrderCreated,
                $user,
                [
                    'customer_name' => $user->name,
                    'order_number' => $order->order_number,
                    'order_id' => $order->id,
                    'order_total' => (string) $order->total,
                    'currency' => $order->currency,
                ],
                channels: [NotificationChannel::InApp, NotificationChannel::WhatsApp],
                idempotencyKey: $key,
                correlationKey: $key,
            );
        } catch (\Throwable $e) {
            $thrown = $e;
        }

        $this->assertNull($thrown);
        $this->assertDatabaseHas('orders', ['id' => $order->id]);
        $this->assertDatabaseHas('notifications', [
            'correlation_key' => 'order_created:'.$order->id.':'.$user->id,
            'channel' => 'in_app',
            'status' => 'sent',
        ]);
        $this->assertDatabaseHas('notifications', [
            'correlation_key' => 'order_created:'.$order->id.':'.$user->id,
            'channel' => 'whatsapp',
            'status' => 'failed',
        ]);
    }

    public function test_payment_notify_failure_does_not_throw_or_delete_order(): void
    {
        $this->configureWhatsApp();
        Http::fake([
            'graph.facebook.com/*' => Http::response(['error' => ['message' => 'down']], 500),
        ]);

        $user = User::factory()->create(['phone' => '+255712345678']);
        $order = Order::factory()->create(['user_id' => $user->id]);

        $thrown = null;
        try {
            $key = 'payment_confirmed:'.$order->id.':'.$user->id;
            app(NotificationPlatform::class)->notifyCustomer(
                NotificationEventType::PaymentConfirmed,
                $user,
                [
                    'customer_name' => $user->name,
                    'order_number' => $order->order_number,
                    'order_id' => $order->id,
                    'order_total' => (string) $order->total,
                    'currency' => $order->currency,
                ],
                channels: [NotificationChannel::WhatsApp],
                idempotencyKey: $key,
                correlationKey: $key,
            );
        } catch (\Throwable $e) {
            $thrown = $e;
        }

        $this->assertNull($thrown);
        $this->assertDatabaseHas('orders', ['id' => $order->id]);
    }

    public function test_idempotency_prevents_duplicate_send(): void
    {
        $this->configureWhatsApp();
        Http::fake([
            'graph.facebook.com/*' => Http::response([
                'messages' => [['id' => 'wamid.ONCE']],
            ], 200),
        ]);

        $user = User::factory()->create(['phone' => '+255712345678']);
        $key = 'payment_confirmed:order-dup:'.$user->id;

        $first = app(NotificationPlatform::class)->notifyCustomer(
            NotificationEventType::PaymentConfirmed,
            $user,
            [
                'customer_name' => 'Asha',
                'order_number' => 'ORD-DUP',
                'order_total' => '1',
                'currency' => 'TZS',
            ],
            channels: [NotificationChannel::WhatsApp],
            idempotencyKey: $key,
            correlationKey: $key,
        );

        $second = app(NotificationPlatform::class)->notifyCustomer(
            NotificationEventType::PaymentConfirmed,
            $user,
            [
                'customer_name' => 'Asha',
                'order_number' => 'ORD-DUP',
                'order_total' => '1',
                'currency' => 'TZS',
            ],
            channels: [NotificationChannel::WhatsApp],
            idempotencyKey: $key,
            correlationKey: $key,
        );

        $this->assertSame($first->first()->id, $second->first()->id);
        Http::assertSentCount(1);
        $this->assertSame(1, Notification::query()->where('correlation_key', $key)->count());
    }

    public function test_only_four_approved_events_are_mapped_and_other_events_stay_in_app_only(): void
    {
        $this->assertSame([
            'order_created',
            'payment_confirmed',
            'shipment_arrived_tanzania',
            'order_delivered',
        ], MetaWhatsAppTemplateMapper::supportedEventTypes());

        $this->assertSame(
            ['in_app', 'whatsapp', 'email'],
            config('notifications.event_channels.order_created'),
        );
        $this->assertSame(
            ['in_app', 'whatsapp', 'email'],
            config('notifications.event_channels.payment_confirmed'),
        );
        $this->assertSame(
            ['in_app', 'whatsapp', 'email'],
            config('notifications.event_channels.shipment_arrived_tanzania'),
        );
        $this->assertSame(
            ['in_app', 'whatsapp', 'email'],
            config('notifications.event_channels.order_delivered'),
        );
        $this->assertSame(
            ['in_app'],
            config('notifications.event_channels.order_cancelled'),
        );
        $this->assertSame(
            ['in_app'],
            config('notifications.event_channels.tracking_updated'),
        );

        $this->configureWhatsApp();
        Http::fake([
            'graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.X']]], 200),
        ]);

        $user = User::factory()->create(['phone' => '+255712345678']);
        $created = app(NotificationPlatform::class)->notifyCustomer(
            NotificationEventType::OrderCancelled,
            $user,
            [
                'customer_name' => 'Asha',
                'order_number' => 'ORD-CANCEL',
            ],
        );

        $this->assertTrue($created->every(fn (Notification $n) => $n->channel === NotificationChannel::InApp));
        Http::assertNothingSent();
    }

    public function test_template_parameter_order_for_all_four_events(): void
    {
        $mapper = app(MetaWhatsAppTemplateMapper::class);

        $orderCreated = $mapper->map($this->makeWhatsAppNotification(event: NotificationEventType::OrderCreated));
        $this->assertSame('order_created', $orderCreated['name']);
        $this->assertSame(['Asha', 'ORD-1', '1000', 'TZS'], $orderCreated['body_parameters']);

        $payment = $mapper->map($this->makeWhatsAppNotification(event: NotificationEventType::PaymentConfirmed));
        $this->assertSame('payment_confirmed', $payment['name']);
        $this->assertSame(['Asha', 'ORD-1', '1000', 'TZS'], $payment['body_parameters']);

        $arrived = Notification::factory()->create([
            'channel' => NotificationChannel::WhatsApp,
            'event_type' => NotificationEventType::ShipmentArrivedTanzania->value,
            'data' => [
                'customer_name' => 'Asha',
                'order_number' => 'ORD-1',
                'location' => 'DSM Warehouse',
            ],
        ]);
        $arrivedMapped = $mapper->map($arrived);
        $this->assertSame('shipment_arrived_tanzania', $arrivedMapped['name']);
        $this->assertSame(['Asha', 'ORD-1', 'DSM Warehouse'], $arrivedMapped['body_parameters']);

        $delivered = Notification::factory()->create([
            'channel' => NotificationChannel::WhatsApp,
            'event_type' => NotificationEventType::OrderDelivered->value,
            'data' => [
                'customer_name' => 'Asha',
                'order_number' => 'ORD-1',
            ],
        ]);
        $deliveredMapped = $mapper->map($delivered);
        $this->assertSame('order_delivered', $deliveredMapped['name']);
        $this->assertSame(['Asha', 'ORD-1'], $deliveredMapped['body_parameters']);
    }

    public function test_access_token_never_appears_in_error_text(): void
    {
        $this->configureWhatsApp();
        Http::fake([
            'graph.facebook.com/*' => Http::response([
                'error' => [
                    'message' => 'Auth failed for '.self::TOKEN,
                    'code' => 190,
                ],
            ], 401),
        ]);

        $user = User::factory()->create(['phone' => '+255712345678']);
        $created = app(NotificationPlatform::class)->notifyCustomer(
            NotificationEventType::OrderCreated,
            $user,
            [
                'customer_name' => 'Asha',
                'order_number' => 'ORD-SEC',
                'order_total' => '1',
                'currency' => 'TZS',
            ],
            channels: [NotificationChannel::WhatsApp],
        );

        $error = (string) $created->first()->error_message;
        $this->assertStringNotContainsString(self::TOKEN, $error);
        $this->assertStringContainsString('[redacted]', $error);
    }

    private function configureWhatsApp(): void
    {
        config([
            'notifications.whatsapp.configured' => true,
            'notifications.whatsapp.driver' => 'meta_cloud',
            'notifications.whatsapp.access_token' => self::TOKEN,
            'notifications.whatsapp.phone_number_id' => 'phone-number-id-test',
            'notifications.whatsapp.api_version' => 'v21.0',
            'notifications.whatsapp.default_language' => 'en',
        ]);
    }

    private function makeWhatsAppNotification(
        ?User $user = null,
        NotificationEventType $event = NotificationEventType::OrderCreated,
    ): Notification {
        $user ??= User::factory()->create(['phone' => '+255712345678']);

        return Notification::factory()->create([
            'user_id' => $user->id,
            'customer_id' => $user->id,
            'channel' => NotificationChannel::WhatsApp,
            'event_type' => $event->value,
            'type' => $event->value,
            'data' => [
                'customer_name' => 'Asha',
                'order_number' => 'ORD-1',
                'order_total' => '1000',
                'currency' => 'TZS',
            ],
        ]);
    }
}
