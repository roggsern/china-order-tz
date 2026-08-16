<?php

namespace Tests\Feature\Notifications;

use App\Enums\NotificationChannel;
use App\Enums\NotificationDeliveryStatus;
use App\Enums\NotificationEventType;
use App\Mail\PlatformNotificationMail;
use App\Models\Notification;
use App\Models\NotificationTemplate;
use App\Models\Order;
use App\Models\User;
use App\Services\Notifications\DTOs\NotificationEvent;
use App\Services\Notifications\NotificationPlatform;
use App\Services\Notifications\Providers\EmailNotificationProvider;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class EmailChannelCompletionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SettingsSeeder::class);
    }

    public function test_provider_unconfigured(): void
    {
        config([
            'notifications.email.configured' => false,
            'mail.from.address' => 'noreply@example.com',
        ]);
        Mail::fake();

        $result = app(EmailNotificationProvider::class)->send($this->makeEmailNotification());

        $this->assertFalse($result['success']);
        $this->assertSame('Not Configured', $result['error']);
        Mail::assertNothingSent();
    }

    public function test_smtp_configured_flag_requires_from_address(): void
    {
        config([
            'notifications.email.configured' => true,
            'mail.from.address' => '',
        ]);

        $this->assertFalse(app(EmailNotificationProvider::class)->isConfigured());
    }

    public function test_customer_missing(): void
    {
        $this->configureEmail();
        Mail::fake();

        $notification = Notification::factory()->create([
            'user_id' => null,
            'customer_id' => null,
            'channel' => NotificationChannel::Email,
            'event_type' => NotificationEventType::OrderCreated->value,
            'title' => 'Order received',
            'message' => 'Hello',
        ]);

        $result = app(EmailNotificationProvider::class)->send($notification);

        $this->assertFalse($result['success']);
        $this->assertSame('Customer missing', $result['error']);
        Mail::assertNothingSent();
    }

    public function test_customer_email_missing(): void
    {
        $this->configureEmail();
        Mail::fake();

        $user = User::factory()->create(['email' => '']);
        // Factory may force email; ensure blank after create
        $user->forceFill(['email' => ''])->saveQuietly();

        $result = app(EmailNotificationProvider::class)->send($this->makeEmailNotification($user));

        $this->assertFalse($result['success']);
        $this->assertSame('Customer email missing', $result['error']);
        Mail::assertNothingSent();
    }

    public function test_invalid_email(): void
    {
        $this->configureEmail();
        Mail::fake();

        $user = User::factory()->create();
        $user->forceFill(['email' => 'not-an-email'])->saveQuietly();

        $result = app(EmailNotificationProvider::class)->send($this->makeEmailNotification($user));

        $this->assertFalse($result['success']);
        $this->assertSame('Invalid email address', $result['error']);
        Mail::assertNothingSent();
    }

    public function test_successful_send_persists_history_and_masked_recipient(): void
    {
        $this->configureEmail();
        Mail::fake();
        $this->seedEmailTemplate(NotificationEventType::OrderCreated);

        $user = User::factory()->create([
            'name' => 'Asha',
            'email' => 'asha@example.com',
        ]);

        $created = app(NotificationPlatform::class)->notifyCustomer(
            NotificationEventType::OrderCreated,
            $user,
            [
                'customer_name' => 'Asha',
                'order_number' => 'ORD-100',
                'order_total' => '50000',
                'currency' => 'TZS',
            ],
            channels: [NotificationChannel::Email],
            idempotencyKey: 'order_created:email-success:'.$user->id,
        );

        $notification = $created->first();
        $this->assertNotNull($notification);
        $this->assertSame(NotificationDeliveryStatus::Sent, $notification->status);
        $this->assertNotNull($notification->provider_message_id);
        $this->assertSame('a***@example.com', $notification->data['email_recipient_masked'] ?? null);
        $this->assertStringContainsString('ORD-100', (string) $notification->message);
        Mail::assertSent(PlatformNotificationMail::class, 1);
    }

    public function test_transport_exception_marks_failed(): void
    {
        $this->configureEmail();
        Mail::shouldReceive('to')
            ->once()
            ->andThrow(new \RuntimeException('SMTP connection refused'));

        $user = User::factory()->create(['email' => 'asha@example.com']);
        $created = app(NotificationPlatform::class)->notifyCustomer(
            NotificationEventType::PaymentConfirmed,
            $user,
            [
                'customer_name' => 'Asha',
                'order_number' => 'ORD-200',
                'order_total' => '1000',
                'currency' => 'TZS',
            ],
            channels: [NotificationChannel::Email],
        );

        $notification = $created->first();
        $this->assertSame(NotificationDeliveryStatus::Failed, $notification->status);
        $this->assertStringContainsString('SMTP connection refused', (string) $notification->error_message);
    }

    public function test_in_app_still_succeeds_when_email_fails(): void
    {
        $this->configureEmail();
        Mail::shouldReceive('to')
            ->once()
            ->andThrow(new \RuntimeException('transport down'));

        $this->seedEmailTemplate(NotificationEventType::OrderCreated);
        NotificationTemplate::factory()->create([
            'key' => 'order_created.in_app',
            'channel' => NotificationChannel::InApp,
            'subject' => 'Order {{order_number}}',
            'body' => 'In-app {{order_number}}',
            'is_active' => true,
        ]);

        $user = User::factory()->create(['email' => 'asha@example.com']);
        $created = app(NotificationPlatform::class)->notifyCustomer(
            NotificationEventType::OrderCreated,
            $user,
            [
                'customer_name' => 'Asha',
                'order_number' => 'ORD-500',
                'order_total' => '10',
                'currency' => 'TZS',
            ],
            channels: [NotificationChannel::InApp, NotificationChannel::Email],
            idempotencyKey: 'order_created:dual-email:'.$user->id,
        );

        $inApp = $created->first(fn (Notification $n) => $n->channel === NotificationChannel::InApp);
        $email = $created->first(fn (Notification $n) => $n->channel === NotificationChannel::Email);

        $this->assertSame(NotificationDeliveryStatus::Sent, $inApp->status);
        $this->assertSame(NotificationDeliveryStatus::Failed, $email->status);
    }

    public function test_order_payment_arrival_delivery_notify_failures_do_not_throw(): void
    {
        $this->configureEmail();
        Mail::shouldReceive('to')->andThrow(new \RuntimeException('smtp down'));

        $user = User::factory()->create(['email' => 'asha@example.com']);
        $order = Order::factory()->create(['user_id' => $user->id]);

        foreach ([
            [NotificationEventType::OrderCreated, 'order_created:'.$order->id.':'.$user->id, [
                'customer_name' => $user->name,
                'order_number' => $order->order_number,
                'order_total' => (string) $order->total,
                'currency' => $order->currency,
            ]],
            [NotificationEventType::PaymentConfirmed, 'payment_confirmed:'.$order->id.':'.$user->id, [
                'customer_name' => $user->name,
                'order_number' => $order->order_number,
                'order_total' => (string) $order->total,
                'currency' => $order->currency,
            ]],
            [NotificationEventType::ShipmentArrivedTanzania, 'shipment_arrived_tanzania:'.$order->id.':'.$user->id, [
                'customer_name' => $user->name,
                'order_number' => $order->order_number,
                'location' => 'Dar es Salaam',
            ]],
            [NotificationEventType::OrderDelivered, 'order_delivered:'.$order->id.':'.$user->id, [
                'customer_name' => $user->name,
                'order_number' => $order->order_number,
            ]],
        ] as [$type, $key, $data]) {
            $thrown = null;
            try {
                app(NotificationPlatform::class)->notifyCustomer(
                    $type,
                    $user,
                    $data,
                    channels: [NotificationChannel::Email],
                    idempotencyKey: $key,
                    correlationKey: $key,
                );
            } catch (\Throwable $e) {
                $thrown = $e;
            }

            $this->assertNull($thrown, $type->value.' threw');
        }

        $this->assertDatabaseHas('orders', ['id' => $order->id]);
    }

    public function test_idempotency_prevents_duplicate_email_send(): void
    {
        $this->configureEmail();
        Mail::fake();
        $this->seedEmailTemplate(NotificationEventType::PaymentConfirmed);

        $user = User::factory()->create(['email' => 'asha@example.com']);
        $key = 'payment_confirmed:email-dup:'.$user->id;

        $first = app(NotificationPlatform::class)->notifyCustomer(
            NotificationEventType::PaymentConfirmed,
            $user,
            [
                'customer_name' => 'Asha',
                'order_number' => 'ORD-DUP',
                'order_total' => '1',
                'currency' => 'TZS',
            ],
            channels: [NotificationChannel::Email],
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
            channels: [NotificationChannel::Email],
            idempotencyKey: $key,
            correlationKey: $key,
        );

        $this->assertSame($first->first()->id, $second->first()->id);
        Mail::assertSent(PlatformNotificationMail::class, 1);
        $this->assertSame(1, Notification::query()->where('correlation_key', $key)->count());
    }

    public function test_only_approved_events_list_email_in_config(): void
    {
        $this->assertContains('email', config('notifications.event_channels.order_created'));
        $this->assertContains('email', config('notifications.event_channels.payment_confirmed'));
        $this->assertContains('email', config('notifications.event_channels.shipment_arrived_tanzania'));
        $this->assertContains('email', config('notifications.event_channels.order_delivered'));
        $this->assertNotContains('email', config('notifications.event_channels.order_cancelled'));
        $this->assertNotContains('email', config('notifications.event_channels.tracking_updated'));
    }

    public function test_platform_email_when_unconfigured_returns_failed(): void
    {
        config(['notifications.email.configured' => false]);
        Mail::fake();

        NotificationTemplate::factory()->create([
            'key' => 'password_reset.email',
            'channel' => NotificationChannel::Email,
            'subject' => 'Reset',
            'body' => 'Code {{reset_code}}',
            'is_active' => true,
        ]);

        $user = User::factory()->create();
        $created = app(NotificationPlatform::class)->publish(new NotificationEvent(
            type: NotificationEventType::PasswordReset,
            data: ['reset_code' => '123456', 'customer_name' => 'Asha'],
            customerId: $user->id,
            channels: [NotificationChannel::Email],
        ));

        $this->assertSame(NotificationDeliveryStatus::Failed, $created->first()->status);
        $this->assertSame('Not Configured', $created->first()->error_message);
        Mail::assertNothingSent();
    }

    public function test_provider_key_follows_notification_email_driver_resend(): void
    {
        config([
            'notifications.email.configured' => true,
            'notifications.email.driver' => 'resend',
            'mail.from.address' => 'orders@chinaordertz.com',
        ]);

        $this->assertSame('resend', app(EmailNotificationProvider::class)->providerKey());
    }

    public function test_resend_api_key_is_redacted_from_delivery_error(): void
    {
        $secret = 're_test_secret_key_do_not_persist';
        config([
            'notifications.email.configured' => true,
            'notifications.email.driver' => 'resend',
            'mail.default' => 'array',
            'mail.from.address' => 'orders@chinaordertz.com',
            'services.resend.key' => $secret,
        ]);

        Mail::shouldReceive('to')
            ->once()
            ->andThrow(new \RuntimeException('Resend rejected Authorization '.$secret.' for request'));

        $user = User::factory()->create(['email' => 'asha@example.com']);
        $result = app(EmailNotificationProvider::class)->send($this->makeEmailNotification($user));

        $this->assertFalse($result['success']);
        $this->assertNotNull($result['error']);
        $this->assertStringNotContainsString($secret, (string) $result['error']);
        $this->assertStringContainsString('[redacted]', (string) $result['error']);
    }

    private function configureEmail(): void
    {
        config([
            'notifications.email.configured' => true,
            'notifications.email.driver' => 'smtp',
            'mail.default' => 'array',
            'mail.from.address' => 'noreply@chinaordertz.com',
            'mail.from.name' => 'CHINA ORDER TZ',
        ]);
    }

    private function seedEmailTemplate(NotificationEventType $event): void
    {
        NotificationTemplate::factory()->create([
            'key' => $event->value.'.email',
            'channel' => NotificationChannel::Email,
            'subject' => 'Subject {{order_number}}',
            'body' => 'Hello {{customer_name}}, order {{order_number}} total {{order_total}} {{currency}} at {{location}}.',
            'is_active' => true,
        ]);
    }

    private function makeEmailNotification(?User $user = null): Notification
    {
        $user ??= User::factory()->create(['email' => 'asha@example.com']);

        return Notification::factory()->create([
            'user_id' => $user->id,
            'customer_id' => $user->id,
            'channel' => NotificationChannel::Email,
            'event_type' => NotificationEventType::OrderCreated->value,
            'type' => NotificationEventType::OrderCreated->value,
            'title' => 'Order received',
            'message' => 'Hello Asha',
            'data' => [
                'customer_name' => 'Asha',
                'order_number' => 'ORD-1',
            ],
        ]);
    }
}
