<?php

namespace Tests\Feature\Storefront;

use App\Enums\StorefrontEventType;
use App\Models\Product;
use App\Models\StorefrontEvent;
use App\Models\StorefrontSession;
use App\Models\StorefrontVisitor;
use App\Services\Reporting\DTOs\ReportPeriod;
use App\Services\Storefront\StorefrontAnalyticsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StorefrontAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    public function test_daily_snapshot_counts_visitors_sessions_and_new_vs_returning(): void
    {
        $today = now()->startOfDay();
        $yesterday = $today->copy()->subDay();

        $returningVisitor = StorefrontVisitor::query()->create([
            'visitor_uuid' => '11111111-1111-4111-8111-111111111111',
            'first_seen_at' => $yesterday,
            'last_seen_at' => $today,
        ]);
        $newVisitor = StorefrontVisitor::query()->create([
            'visitor_uuid' => '22222222-2222-4222-8222-222222222222',
            'first_seen_at' => $today,
            'last_seen_at' => $today,
        ]);

        $returningSession = StorefrontSession::query()->create([
            'visitor_id' => $returningVisitor->id,
            'started_at' => $today,
            'last_activity_at' => $today,
        ]);
        $newSession = StorefrontSession::query()->create([
            'visitor_id' => $newVisitor->id,
            'started_at' => $today,
            'last_activity_at' => $today,
        ]);

        $this->createEvent($returningVisitor, $returningSession, StorefrontEventType::PageView, [
            'path' => '/products',
            'created_at' => $today->copy()->addHour(),
        ]);
        $this->createEvent($newVisitor, $newSession, StorefrontEventType::PageView, [
            'path' => '/',
            'created_at' => $today->copy()->addHours(2),
        ]);
        $this->createEvent($newVisitor, $newSession, StorefrontEventType::PageView, [
            'path' => '/cart',
            'created_at' => $today->copy()->addHours(3),
        ]);

        $snapshot = app(StorefrontAnalyticsService::class)->dailySnapshot($today);

        $this->assertSame(2, $snapshot['visitors']);
        $this->assertSame(2, $snapshot['sessions']);
        $this->assertSame(1, $snapshot['new_visitors']);
        $this->assertSame(1, $snapshot['returning_visitors']);
    }

    public function test_traffic_aggregates_top_pages_products_and_searches_for_period(): void
    {
        $today = now()->startOfDay();
        $visitor = StorefrontVisitor::query()->create([
            'visitor_uuid' => '33333333-3333-4333-8333-333333333333',
            'first_seen_at' => $today,
            'last_seen_at' => $today,
        ]);
        $session = StorefrontSession::query()->create([
            'visitor_id' => $visitor->id,
            'started_at' => $today,
            'last_activity_at' => $today,
        ]);
        $product = Product::factory()->create(['name' => 'Demo Phone']);

        $this->createEvent($visitor, $session, StorefrontEventType::PageView, [
            'path' => '/products',
            'created_at' => $today->copy()->addHour(),
        ]);
        $this->createEvent($visitor, $session, StorefrontEventType::PageView, [
            'path' => '/products',
            'created_at' => $today->copy()->addHours(2),
        ]);
        $this->createEvent($visitor, $session, StorefrontEventType::ProductViewed, [
            'path' => '/products/demo-phone',
            'product_id' => $product->id,
            'created_at' => $today->copy()->addHours(2),
        ]);
        $this->createEvent($visitor, $session, StorefrontEventType::SearchPerformed, [
            'path' => '/products',
            'metadata' => ['query' => 'iphone'],
            'created_at' => $today->copy()->addHours(3),
        ]);
        $this->createEvent($visitor, $session, StorefrontEventType::SearchPerformed, [
            'path' => '/products',
            'metadata' => ['query' => 'iphone'],
            'created_at' => $today->copy()->addHours(4),
        ]);

        $period = ReportPeriod::fromInput($today->toDateString(), $today->toDateString());
        $traffic = app(StorefrontAnalyticsService::class)->traffic($period);

        $this->assertSame(1, $traffic['visitors_today']);
        $this->assertSame(1, $traffic['sessions_today']);
        $this->assertSame(1, $traffic['new_visitors']);
        $this->assertSame(0, $traffic['returning_visitors']);
        $this->assertSame('/products', $traffic['top_pages'][0]['path']);
        $this->assertSame(2, $traffic['top_pages'][0]['views']);
        $this->assertSame('Demo Phone', $traffic['top_products'][0]['name']);
        $this->assertSame('iphone', $traffic['top_searches'][0]['query']);
        $this->assertSame(2, $traffic['top_searches'][0]['count']);
    }

    public function test_dashboard_includes_storefront_traffic_payload(): void
    {
        \Laravel\Sanctum\Sanctum::actingAs(\App\Models\Admin::factory()->create());

        $today = now()->startOfDay();
        $visitor = StorefrontVisitor::query()->create([
            'visitor_uuid' => '44444444-4444-4444-8444-444444444444',
            'first_seen_at' => $today,
            'last_seen_at' => $today,
        ]);
        $session = StorefrontSession::query()->create([
            'visitor_id' => $visitor->id,
            'started_at' => $today,
            'last_activity_at' => $today,
        ]);

        $this->createEvent($visitor, $session, StorefrontEventType::PageView, [
            'path' => '/',
            'created_at' => $today->copy()->addHour(),
        ]);

        $this->getJson('/api/v1/admin/dashboard?from='.$today->toDateString().'&to='.$today->toDateString())
            ->assertOk()
            ->assertJsonPath('data.storefront_traffic.visitors_today', 1)
            ->assertJsonStructure([
                'data' => [
                    'storefront_traffic' => [
                        'reference_date',
                        'visitors_today',
                        'sessions_today',
                        'new_visitors',
                        'returning_visitors',
                        'growth' => [
                            'visitors_change',
                            'visitors_change_percent',
                            'sessions_change',
                            'sessions_change_percent',
                        ],
                        'top_pages',
                        'top_products',
                        'top_searches',
                    ],
                ],
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
            'user_id' => null,
            'event_type' => $type->value,
            'path' => $attributes['path'] ?? null,
            'product_id' => $attributes['product_id'] ?? null,
            'category_id' => $attributes['category_id'] ?? null,
            'metadata' => $attributes['metadata'] ?? null,
            'created_at' => $attributes['created_at'] ?? now(),
        ]);
    }
}
