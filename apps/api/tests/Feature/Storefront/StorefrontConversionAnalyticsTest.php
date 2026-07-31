<?php

namespace Tests\Feature\Storefront;

use App\Enums\OrderStatus;
use App\Enums\StorefrontEventType;
use App\Events\Audit\PaymentConfirmed;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\StorefrontEvent;
use App\Models\StorefrontSession;
use App\Models\StorefrontVisitor;
use App\Models\User;
use App\Services\Reporting\DTOs\ReportPeriod;
use App\Services\Storefront\StorefrontConversionAnalyticsService;
use App\Services\Storefront\StorefrontEventService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StorefrontConversionAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    public function test_funnel_counts_visitors_through_buyers(): void
    {
        $today = now()->startOfDay();
        $visitorA = $this->createVisitor('aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa', $today);
        $visitorB = $this->createVisitor('bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb', $today);
        $sessionA = $this->createSession($visitorA, $today);
        $sessionB = $this->createSession($visitorB, $today);
        $product = Product::factory()->create(['name' => 'Conversion Phone']);

        $this->createEvent($visitorA, $sessionA, StorefrontEventType::PageView, [
            'path' => '/',
            'created_at' => $today->copy()->addHour(),
        ]);
        $this->createEvent($visitorA, $sessionA, StorefrontEventType::ProductViewed, [
            'product_id' => $product->id,
            'created_at' => $today->copy()->addHours(2),
        ]);
        $this->createEvent($visitorA, $sessionA, StorefrontEventType::AddToCart, [
            'product_id' => $product->id,
            'created_at' => $today->copy()->addHours(3),
        ]);
        $this->createEvent($visitorA, $sessionA, StorefrontEventType::CheckoutStarted, [
            'path' => '/checkout',
            'created_at' => $today->copy()->addHours(4),
        ]);
        $this->createEvent($visitorA, $sessionA, StorefrontEventType::OrderCompleted, [
            'metadata' => [
                'order_id' => fake()->uuid(),
                'order_number' => 'ORD-1001',
                'product_ids' => [$product->id],
            ],
            'created_at' => $today->copy()->addHours(5),
        ]);

        $this->createEvent($visitorB, $sessionB, StorefrontEventType::PageView, [
            'path' => '/products',
            'created_at' => $today->copy()->addHour(),
        ]);
        $this->createEvent($visitorB, $sessionB, StorefrontEventType::ProductViewed, [
            'product_id' => $product->id,
            'created_at' => $today->copy()->addHours(2),
        ]);

        $period = ReportPeriod::fromInput($today->toDateString(), $today->toDateString());
        $conversion = app(StorefrontConversionAnalyticsService::class)->conversion($period);

        $this->assertSame(2, $conversion['funnel']['visitors']);
        $this->assertSame(2, $conversion['funnel']['product_viewers']);
        $this->assertSame(1, $conversion['funnel']['cart_users']);
        $this->assertSame(1, $conversion['funnel']['checkout_users']);
        $this->assertSame(1, $conversion['funnel']['buyers']);
        $this->assertSame(100.0, $conversion['conversion_rates']['visitor_to_product_view']);
        $this->assertSame(50.0, $conversion['conversion_rates']['product_view_to_cart']);
        $this->assertSame(100.0, $conversion['conversion_rates']['cart_to_checkout']);
        $this->assertSame(100.0, $conversion['conversion_rates']['checkout_to_purchase']);
        $this->assertSame(50.0, $conversion['conversion_rates']['visitor_to_purchase']);
    }

    public function test_product_insights_calculate_views_cart_orders_and_conversion(): void
    {
        $today = now()->startOfDay();
        $visitor = $this->createVisitor('cccccccc-cccc-4ccc-8ccc-cccccccccccc', $today);
        $session = $this->createSession($visitor, $today);
        $product = Product::factory()->create(['name' => 'Insight Laptop']);

        $this->createEvent($visitor, $session, StorefrontEventType::ProductViewed, [
            'product_id' => $product->id,
            'created_at' => $today->copy()->addHour(),
        ]);
        $this->createEvent($visitor, $session, StorefrontEventType::ProductViewed, [
            'product_id' => $product->id,
            'created_at' => $today->copy()->addHours(2),
        ]);
        $this->createEvent($visitor, $session, StorefrontEventType::AddToCart, [
            'product_id' => $product->id,
            'created_at' => $today->copy()->addHours(3),
        ]);
        $this->createEvent($visitor, $session, StorefrontEventType::OrderCompleted, [
            'metadata' => [
                'order_id' => fake()->uuid(),
                'order_number' => 'ORD-2002',
                'product_ids' => [$product->id],
            ],
            'created_at' => $today->copy()->addHours(4),
        ]);

        $period = ReportPeriod::fromInput($today->toDateString(), $today->toDateString());
        $insights = app(StorefrontConversionAnalyticsService::class)->productInsights($period);

        $this->assertCount(1, $insights);
        $this->assertSame('Insight Laptop', $insights[0]['name']);
        $this->assertSame(2, $insights[0]['views']);
        $this->assertSame(1, $insights[0]['cart_additions']);
        $this->assertSame(1, $insights[0]['orders']);
        $this->assertSame(50.0, $insights[0]['conversion_rate']);
    }

    public function test_order_completed_is_recorded_from_payment_lifecycle_with_attribution(): void
    {
        $today = now()->startOfDay();
        $user = User::factory()->create();
        $visitor = $this->createVisitor('dddddddd-dddd-4ddd-8ddd-dddddddddddd', $today);
        $session = $this->createSession($visitor, $today, $user);
        $product = Product::factory()->create();

        $order = Order::factory()->create([
            'user_id' => $user->id,
            'status' => OrderStatus::Paid,
            'paid_at' => $today->copy()->addHour(),
            'storefront_visitor_id' => $visitor->id,
            'storefront_session_id' => $session->id,
        ]);
        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
        ]);

        event(PaymentConfirmed::fromOrder($order->fresh(['items'])));

        $this->assertDatabaseHas('storefront_events', [
            'visitor_id' => $visitor->id,
            'session_id' => $session->id,
            'user_id' => $user->id,
            'event_type' => StorefrontEventType::OrderCompleted->value,
        ]);

        $recorded = StorefrontEvent::query()
            ->where('event_type', StorefrontEventType::OrderCompleted->value)
            ->first();

        $this->assertSame($order->id, $recorded?->metadata['order_id']);
        $this->assertContains($product->id, $recorded?->metadata['product_ids'] ?? []);
    }

    public function test_order_completed_event_is_idempotent(): void
    {
        $user = User::factory()->create();
        $visitor = $this->createVisitor('eeeeeeee-eeee-4eee-8eee-eeeeeeeeeeee', now());
        $session = $this->createSession($visitor, now(), $user);
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'status' => OrderStatus::Paid,
            'paid_at' => now(),
            'storefront_visitor_id' => $visitor->id,
            'storefront_session_id' => $session->id,
        ]);

        $service = app(StorefrontEventService::class);
        $first = $service->recordOrderCompleted($order);
        $second = $service->recordOrderCompleted($order);

        $this->assertNotNull($first);
        $this->assertNull($second);
        $this->assertSame(
            1,
            StorefrontEvent::query()
                ->where('event_type', StorefrontEventType::OrderCompleted->value)
                ->where('metadata->order_id', $order->id)
                ->count(),
        );
    }

    public function test_dashboard_includes_storefront_conversion_payload(): void
    {
        Sanctum::actingAs(\App\Models\Admin::factory()->create());

        $today = now()->startOfDay();
        $visitor = $this->createVisitor('ffffffff-ffff-4fff-8fff-ffffffffffff', $today);
        $session = $this->createSession($visitor, $today);

        $this->createEvent($visitor, $session, StorefrontEventType::PageView, [
            'path' => '/',
            'created_at' => $today->copy()->addHour(),
        ]);

        $this->getJson('/api/v1/admin/dashboard?from='.$today->toDateString().'&to='.$today->toDateString())
            ->assertOk()
            ->assertJsonPath('data.storefront_conversion.funnel.visitors', 1)
            ->assertJsonStructure([
                'data' => [
                    'storefront_conversion' => [
                        'funnel' => [
                            'visitors',
                            'product_viewers',
                            'cart_users',
                            'checkout_users',
                            'buyers',
                        ],
                        'conversion_rates' => [
                            'visitor_to_product_view',
                            'product_view_to_cart',
                            'cart_to_checkout',
                            'checkout_to_purchase',
                            'visitor_to_purchase',
                        ],
                        'attribution' => [
                            'orders_with_journey',
                            'attributed_buyers',
                            'first_touch_pages',
                        ],
                        'product_insights',
                    ],
                ],
            ]);
    }

    public function test_rejects_client_submitted_order_completed_events(): void
    {
        $identity = $this->postJson('/api/v1/storefront/visitor/identify', [
            'visitor_uuid' => '12121212-1212-4121-8121-121212121212',
        ])->assertOk();

        $this->postJson('/api/v1/storefront/events', [
            'visitor_uuid' => '12121212-1212-4121-8121-121212121212',
            'session_id' => $identity->json('data.session_id'),
            'event_type' => 'order_completed',
            'metadata' => ['order_id' => fake()->uuid()],
        ])->assertStatus(422);
    }

    private function createVisitor(string $uuid, \DateTimeInterface $seenAt): StorefrontVisitor
    {
        return StorefrontVisitor::query()->create([
            'visitor_uuid' => $uuid,
            'first_seen_at' => $seenAt,
            'last_seen_at' => $seenAt,
        ]);
    }

    private function createSession(
        StorefrontVisitor $visitor,
        \DateTimeInterface $activityAt,
        ?User $user = null,
    ): StorefrontSession {
        return StorefrontSession::query()->create([
            'visitor_id' => $visitor->id,
            'user_id' => $user?->id,
            'started_at' => $activityAt,
            'last_activity_at' => $activityAt,
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createEvent(
        StorefrontVisitor $visitor,
        StorefrontSession $session,
        StorefrontEventType $type,
        array $attributes = [],
    ): StorefrontEvent {
        return StorefrontEvent::query()->create([
            'visitor_id' => $visitor->id,
            'session_id' => $session->id,
            'user_id' => $session->user_id,
            'event_type' => $type->value,
            'path' => $attributes['path'] ?? null,
            'product_id' => $attributes['product_id'] ?? null,
            'category_id' => $attributes['category_id'] ?? null,
            'metadata' => $attributes['metadata'] ?? null,
            'created_at' => $attributes['created_at'] ?? now(),
        ]);
    }
}
