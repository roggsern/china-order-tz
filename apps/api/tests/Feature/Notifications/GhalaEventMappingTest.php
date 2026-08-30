<?php

namespace Tests\Feature\Notifications;

use App\Enums\NotificationChannel;
use App\Enums\NotificationEventType;
use App\Enums\OrderStatus;
use App\Models\Notification;
use App\Models\Order;
use App\Models\Store;
use App\Models\User;
use App\Services\Notifications\NotificationPlatform;
use App\Services\Notifications\WhatsApp\PickupLocationResolver;
use App\Services\Orders\Lifecycle\OrderLifecycleContext;
use App\Services\Orders\Lifecycle\OrderLifecycleEngine;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class GhalaEventMappingTest extends TestCase
{
    use RefreshDatabase;

    private const TOKEN = 'test-ghala-access-token-secret-value';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SettingsSeeder::class);
        config([
            'notifications.whatsapp.configured' => true,
            'notifications.whatsapp.driver' => 'ghala',
            'notifications.whatsapp.access_token' => self::TOKEN,
            'notifications.whatsapp.base_url' => 'https://v2.ghala.io',
            'notifications.whatsapp.default_language' => 'en_US',
            'notifications.whatsapp.retry_attempts' => 1,
            'notifications.whatsapp.retry_sleep_ms' => 0,
        ]);
        Http::fake([
            'https://v2.ghala.io/api/v2/messages' => Http::response([
                'id' => '01MAPPED',
                'status' => 'sent',
            ], 200),
        ]);
    }

    /**
     * @return \Generator<string, array{0: NotificationEventType, 1: string, 2: array<string, mixed>}>
     */
    public static function eventTemplateProvider(): \Generator
    {
        yield 'order_confirmation' => [NotificationEventType::OrderCreated, 'order_confirmation', [
            'order_total' => '1000',
            'currency' => 'TZS',
        ]];
        yield 'payment_received' => [NotificationEventType::PaymentConfirmed, 'payment_received', [
            'order_total' => '1000',
            'currency' => 'TZS',
        ]];
        yield 'order_processing' => [NotificationEventType::OrderProcessing, 'order_processing', []];
        yield 'order_arrived_tanzania' => [NotificationEventType::ShipmentArrivedTanzania, 'order_arrived_tanzania', [
            'location' => 'Dar es Salaam',
        ]];
        yield 'order_ready_for_pickup' => [NotificationEventType::WarehouseReadyForPickup, 'order_ready_for_pickup', [
            'pickup_location' => 'Kariakoo Collection Desk',
        ]];
        yield 'order_shipped' => [NotificationEventType::ShipmentCreated, 'order_shipped', [
            'destination' => 'Arusha',
        ]];
        yield 'order_delivered' => [NotificationEventType::OrderDelivered, 'order_delivered', []];
        yield 'order_cancelled' => [NotificationEventType::OrderCancelled, 'order_cancelled', []];
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    #[DataProvider('eventTemplateProvider')]
    public function test_event_selects_approved_template(
        NotificationEventType $event,
        string $template,
        array $extra,
    ): void {
        $user = User::factory()->create(['phone' => '+255712345678', 'name' => 'Asha']);

        app(NotificationPlatform::class)->notifyCustomer(
            $event,
            $user,
            array_merge([
                'customer_name' => 'Asha',
                'order_number' => 'ORD-MAP',
            ], $extra),
            channels: [NotificationChannel::WhatsApp],
            idempotencyKey: $event->value.':map:'.$user->id,
        );

        Http::assertSent(fn ($request) => ($request->data()['template_name'] ?? null) === $template
            && ($request->data()['template_language'] ?? null) === 'en_US');
    }

    public function test_processing_notifies_once_on_legitimate_transition_only(): void
    {
        $user = User::factory()->create(['phone' => '+255712345678']);
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'status' => OrderStatus::Paid,
            'paid_at' => now(),
        ]);

        $engine = app(OrderLifecycleEngine::class);
        $engine->transition($order, OrderStatus::Processing, OrderLifecycleContext::fulfillment('start processing'));
        $engine->transition($order->fresh(), OrderStatus::Processing, OrderLifecycleContext::fulfillment('still processing'));

        $this->assertSame(OrderStatus::Processing, $order->fresh()->status);
        $this->assertSame(1, Notification::query()
            ->where('event_type', NotificationEventType::OrderProcessing->value)
            ->where('customer_id', $user->id)
            ->count());
    }

    public function test_pickup_location_comes_from_store_business_address(): void
    {
        $user = User::factory()->create(['phone' => '+255712345678']);
        $store = Store::query()->create([
            'code' => 'PICK1',
            'name' => 'Pickup Store',
            'slug' => 'pickup-store',
            'is_active' => true,
            'settings' => [
                'business' => ['address' => 'Plot 12, Kariakoo, Dar es Salaam'],
            ],
        ]);
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'store_id' => $store->id,
        ]);

        $location = app(PickupLocationResolver::class)->forOrder($order);

        $this->assertSame('Plot 12, Kariakoo, Dar es Salaam', $location);
    }
}
