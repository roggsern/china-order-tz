<?php

namespace Tests\Feature\Notifications;

use App\Enums\NotificationChannel;
use App\Enums\NotificationDeliveryStatus;
use App\Enums\NotificationEventType;
use App\Enums\OrderStatus;
use App\Models\Notification;
use App\Models\Order;
use App\Models\User;
use App\Services\Notifications\NotificationPlatform;
use App\Services\Orders\Lifecycle\OrderLifecycleContext;
use App\Services\Orders\Lifecycle\OrderLifecycleEngine;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GhalaFailureIsolationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SettingsSeeder::class);
        config([
            'notifications.whatsapp.configured' => true,
            'notifications.whatsapp.driver' => 'ghala',
            'notifications.whatsapp.access_token' => 'test-ghala-access-token-secret-value',
            'notifications.whatsapp.base_url' => 'https://v2.ghala.io',
            'notifications.whatsapp.retry_attempts' => 1,
            'notifications.whatsapp.retry_sleep_ms' => 0,
        ]);
        Http::fake([
            'https://v2.ghala.io/api/v2/messages' => Http::response([
                'code' => 'unavailable',
                'message' => 'Ghala down',
            ], 503),
        ]);
    }

    public function test_ghala_outage_does_not_fail_order_processing_transition(): void
    {
        $user = User::factory()->create(['phone' => '+255712345678']);
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'status' => OrderStatus::Paid,
            'paid_at' => now(),
        ]);

        $result = app(OrderLifecycleEngine::class)->transition(
            $order,
            OrderStatus::Processing,
            OrderLifecycleContext::fulfillment('warehouse started'),
        );

        $this->assertSame(OrderStatus::Processing, $result->status);
        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => OrderStatus::Processing->value,
        ]);
    }

    public function test_ghala_outage_does_not_fail_payment_notification_or_order(): void
    {
        $user = User::factory()->create(['phone' => '+255712345678']);
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'status' => OrderStatus::Paid,
            'paid_at' => now(),
        ]);

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
                channels: [NotificationChannel::InApp, NotificationChannel::WhatsApp],
                idempotencyKey: $key,
                correlationKey: $key,
            );
        } catch (\Throwable $e) {
            $thrown = $e;
        }

        $this->assertNull($thrown);
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => OrderStatus::Paid->value]);
        $this->assertDatabaseHas('notifications', [
            'correlation_key' => 'payment_confirmed:'.$order->id.':'.$user->id,
            'channel' => 'in_app',
            'status' => 'sent',
        ]);
        $this->assertDatabaseHas('notifications', [
            'correlation_key' => 'payment_confirmed:'.$order->id.':'.$user->id,
            'channel' => 'whatsapp',
            'status' => 'failed',
        ]);
    }

    public function test_other_channels_continue_when_whatsapp_fails(): void
    {
        $user = User::factory()->create(['phone' => '+255712345678']);
        $created = app(NotificationPlatform::class)->notifyCustomer(
            NotificationEventType::OrderCancelled,
            $user,
            ['customer_name' => 'Asha', 'order_number' => 'ORD-C'],
            channels: [NotificationChannel::InApp, NotificationChannel::WhatsApp],
            idempotencyKey: 'order-cancelled:isolation',
        );

        $this->assertSame(
            NotificationDeliveryStatus::Sent,
            $created->first(fn (Notification $n) => $n->channel === NotificationChannel::InApp)?->status,
        );
        $this->assertSame(
            NotificationDeliveryStatus::Failed,
            $created->first(fn (Notification $n) => $n->channel === NotificationChannel::WhatsApp)?->status,
        );
    }
}
