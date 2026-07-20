<?php

namespace Tests\Feature\Notifications;

use App\Enums\NotificationChannel;
use App\Enums\NotificationDeliveryStatus;
use App\Enums\NotificationEventType;
use App\Models\Admin;
use App\Models\Notification;
use App\Models\NotificationTemplate;
use App\Models\User;
use App\Services\Notifications\ChannelProviderRegistry;
use App\Services\Notifications\DTOs\NotificationEvent;
use App\Services\Notifications\NotificationDispatcher;
use App\Services\Notifications\NotificationPlatform;
use App\Services\Notifications\NotificationRenderer;
use App\Services\Notifications\NotificationTemplateEngine;
use App\Services\Notifications\Providers\EmailNotificationProvider;
use App\Services\Notifications\Providers\InAppNotificationProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class NotificationPlatformTest extends TestCase
{
    use RefreshDatabase;

    public function test_renderer_replaces_variables(): void
    {
        $renderer = new NotificationRenderer;

        $result = $renderer->render(
            'Hello {{customer_name}}, order {{order_number}} shipped.',
            ['customer_name' => 'Asha', 'order_number' => 'ORD-1'],
        );

        $this->assertSame('Hello Asha, order ORD-1 shipped.', $result);
    }

    public function test_dispatcher_creates_in_app_notification_from_template(): void
    {
        $user = User::factory()->create(['name' => 'Asha']);

        NotificationTemplate::factory()->create([
            'key' => 'order_created.in_app',
            'channel' => NotificationChannel::InApp,
            'subject' => 'Order {{order_number}}',
            'body' => 'Hello {{customer_name}}, order {{order_number}} created.',
            'is_active' => true,
        ]);

        $platform = app(NotificationPlatform::class);
        $created = $platform->publish(new NotificationEvent(
            type: NotificationEventType::OrderCreated,
            data: [
                'customer_name' => 'Asha',
                'order_number' => 'ORD-100',
            ],
            customerId: $user->id,
        ));

        $this->assertCount(1, $created);
        $notification = $created->first();
        $this->assertSame(NotificationDeliveryStatus::Sent, $notification->status);
        $this->assertSame(NotificationChannel::InApp, $notification->channel);
        $this->assertSame('in_app', $notification->provider);
        $this->assertStringContainsString('Asha', $notification->message);
        $this->assertStringContainsString('ORD-100', $notification->message);
        $this->assertDatabaseHas('notifications', [
            'customer_id' => $user->id,
            'event_type' => NotificationEventType::OrderCreated->value,
            'channel' => 'in_app',
            'status' => 'sent',
        ]);
    }

    public function test_email_provider_returns_not_configured(): void
    {
        $user = User::factory()->create();

        NotificationTemplate::factory()->create([
            'key' => 'password_reset.email',
            'channel' => NotificationChannel::Email,
            'subject' => 'Reset',
            'body' => 'Code {{reset_code}}',
            'is_active' => true,
        ]);

        $platform = app(NotificationPlatform::class);
        $created = $platform->publish(new NotificationEvent(
            type: NotificationEventType::PasswordReset,
            data: ['reset_code' => '123456', 'customer_name' => 'Asha'],
            customerId: $user->id,
            channels: [NotificationChannel::Email],
        ));

        $notification = $created->first();
        $this->assertSame(NotificationDeliveryStatus::Failed, $notification->status);
        $this->assertSame('Not Configured', $notification->error_message);
        $this->assertSame(
            app(EmailNotificationProvider::class)->providerKey(),
            $notification->provider,
        );
    }

    public function test_channel_selection_from_config(): void
    {
        config(['notifications.event_channels.otp_requested' => ['in_app', 'sms']]);

        NotificationTemplate::factory()->create([
            'key' => 'otp_requested.in_app',
            'channel' => NotificationChannel::InApp,
            'body' => 'OTP {{otp_code}}',
            'is_active' => true,
        ]);
        NotificationTemplate::factory()->create([
            'key' => 'otp_requested.sms',
            'channel' => NotificationChannel::Sms,
            'body' => 'OTP {{otp_code}}',
            'is_active' => true,
        ]);

        $user = User::factory()->create();
        $created = app(NotificationPlatform::class)->notifyCustomer(
            NotificationEventType::OtpRequested,
            $user,
            ['otp_code' => '999111', 'customer_name' => 'Asha'],
        );

        $this->assertCount(2, $created);
        $this->assertTrue($created->contains(fn (Notification $n) => $n->channel === NotificationChannel::InApp));
        $this->assertTrue($created->contains(fn (Notification $n) => $n->channel === NotificationChannel::Sms));
    }

    public function test_provider_resolution_via_registry(): void
    {
        $registry = app(ChannelProviderRegistry::class);

        $this->assertTrue($registry->has(NotificationChannel::InApp));
        $this->assertTrue($registry->has(NotificationChannel::WhatsApp));
        $this->assertInstanceOf(InAppNotificationProvider::class, $registry->resolve(NotificationChannel::InApp));
        $this->assertSame('in_app', $registry->resolve('in_app')->providerKey());
    }

    public function test_customer_mark_all_as_read(): void
    {
        $user = User::factory()->create();
        Notification::factory()->count(3)->unread()->create([
            'user_id' => $user->id,
            'customer_id' => $user->id,
        ]);

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/notifications/read-all')
            ->assertOk()
            ->assertJsonPath('data.marked', 3);

        $this->assertSame(0, app(\App\Services\Notifications\NotificationService::class)->unreadCount($user));
    }

    public function test_admin_notification_log_and_templates(): void
    {
        Sanctum::actingAs(Admin::factory()->create());

        $template = NotificationTemplate::factory()->create([
            'key' => 'order_created.in_app',
            'channel' => NotificationChannel::InApp,
            'body' => 'Hello {{customer_name}}',
            'is_active' => true,
        ]);

        Notification::factory()->create([
            'event_type' => NotificationEventType::OrderCreated->value,
            'channel' => NotificationChannel::InApp,
        ]);

        $this->getJson('/api/v1/admin/notifications')
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->getJson('/api/v1/admin/notification-templates')
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->postJson("/api/v1/admin/notification-templates/{$template->id}/preview", [
            'variables' => ['customer_name' => 'Asha'],
        ])
            ->assertOk()
            ->assertJsonPath('data.rendered.body', 'Hello Asha');

        $this->putJson("/api/v1/admin/notification-templates/{$template->id}", [
            'is_active' => false,
            'body' => 'Updated {{customer_name}}',
        ])
            ->assertOk()
            ->assertJsonPath('data.is_active', false)
            ->assertJsonPath('data.body', 'Updated {{customer_name}}');
    }

    public function test_customer_cannot_access_admin_notification_log(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/v1/admin/notifications')->assertUnauthorized();
    }

    public function test_notification_relationships(): void
    {
        $user = User::factory()->create();
        $admin = Admin::factory()->create();

        $notification = Notification::factory()->create([
            'user_id' => $user->id,
            'customer_id' => $user->id,
            'admin_id' => $admin->id,
        ]);

        $this->assertTrue($notification->customer->is($user));
        $this->assertTrue($notification->admin->is($admin));
        $this->assertTrue($notification->user->is($user));
    }

    public function test_template_engine_resolves_by_event_and_channel(): void
    {
        NotificationTemplate::factory()->create([
            'key' => 'shipment_created.in_app',
            'channel' => NotificationChannel::InApp,
            'body' => 'Ship {{shipment_number}}',
            'is_active' => true,
        ]);

        $engine = app(NotificationTemplateEngine::class);
        $template = $engine->resolveForEvent(
            NotificationEventType::ShipmentCreated,
            NotificationChannel::InApp,
        );

        $this->assertNotNull($template);
        $this->assertSame('shipment_created.in_app', $template->key);
    }

    public function test_dispatcher_is_bound_without_hardcoded_providers(): void
    {
        $dispatcher = app(NotificationDispatcher::class);
        $this->assertInstanceOf(NotificationDispatcher::class, $dispatcher);

        $registry = app(ChannelProviderRegistry::class);
        $this->assertCount(5, $registry->all());
    }
}
