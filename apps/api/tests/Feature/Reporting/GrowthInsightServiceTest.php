<?php

namespace Tests\Feature\Reporting;

use App\Enums\GrowthInsightSeverity;
use App\Enums\StorefrontEventType;
use App\Models\Product;
use App\Models\StorefrontEvent;
use App\Models\StorefrontSession;
use App\Models\StorefrontVisitor;
use App\Services\Reporting\DTOs\ReportPeriod;
use App\Services\Reporting\GrowthInsightService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class GrowthInsightServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_detects_traffic_increase_with_conversion_decrease(): void
    {
        $currentStart = now()->startOfDay();
        $currentEnd = $currentStart->copy()->endOfDay();
        $previousStart = $currentStart->copy()->subDay()->startOfDay();
        $previousEnd = $previousStart->copy()->endOfDay();

        $this->seedVisitorFunnel($previousStart, visitors: 10, buyers: 2);
        $this->seedVisitorFunnel($currentStart, visitors: 12, buyers: 2);

        $period = new ReportPeriod($currentStart, $currentEnd);
        $result = app(GrowthInsightService::class)->intelligence($period);

        $this->assertSame('watch', $result['health_status']);
        $this->assertTrue(collect($result['alerts'])->contains(
            static fn (array $alert) => $alert['type'] === 'traffic_up_conversion_down'
                && $alert['severity'] === GrowthInsightSeverity::Medium->value,
        ));
    }

    public function test_detects_high_views_low_orders_product_warning(): void
    {
        $day = now()->startOfDay();
        $visitor = $this->createVisitor('aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa', $day);
        $session = $this->createSession($visitor, $day);
        $product = Product::factory()->create(['name' => 'Gap Phone', 'slug' => 'other-phone']);

        for ($i = 0; $i < 6; $i++) {
            $this->createEvent($visitor, $session, StorefrontEventType::ProductViewed, [
                'product_id' => $product->id,
                'created_at' => $day->copy()->addMinutes($i + 1),
            ]);
        }

        $period = new ReportPeriod($day, $day->copy()->endOfDay());
        $result = app(GrowthInsightService::class)->intelligence($period);

        $this->assertTrue(collect($result['alerts'])->contains(
            static fn (array $alert) => $alert['type'] === 'high_views_low_orders'
                && str_contains($alert['message'], 'Gap Phone'),
        ));
    }

    public function test_detects_search_demand_without_catalog_match(): void
    {
        $day = now()->startOfDay();
        $visitor = $this->createVisitor('bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb', $day);
        $session = $this->createSession($visitor, $day);

        for ($i = 0; $i < 4; $i++) {
            $this->createEvent($visitor, $session, StorefrontEventType::SearchPerformed, [
                'metadata' => ['query' => 'unicorn laptop'],
                'created_at' => $day->copy()->addMinutes($i + 1),
            ]);
        }

        $period = new ReportPeriod($day, $day->copy()->endOfDay());
        $result = app(GrowthInsightService::class)->intelligence($period);

        $this->assertTrue(collect($result['alerts'])->contains(
            static fn (array $alert) => $alert['type'] === 'search_demand_gap'
                && $alert['severity'] === GrowthInsightSeverity::Medium->value,
        ));
    }

    public function test_detects_conversion_drop_without_traffic_surge(): void
    {
        $currentStart = now()->startOfDay();
        $previousStart = $currentStart->copy()->subDay();

        $this->seedVisitorFunnel($previousStart, visitors: 20, buyers: 4);
        $this->seedVisitorFunnel($currentStart, visitors: 20, buyers: 1);

        $period = new ReportPeriod($currentStart, $currentStart->copy()->endOfDay());
        $result = app(GrowthInsightService::class)->intelligence($period);

        $this->assertSame('at_risk', $result['health_status']);
        $this->assertTrue(collect($result['alerts'])->contains(
            static fn (array $alert) => $alert['type'] === 'conversion_drop'
                && $alert['severity'] === GrowthInsightSeverity::High->value,
        ));
    }

    public function test_dashboard_includes_growth_intelligence_payload(): void
    {
        Sanctum::actingAs(\App\Models\Admin::factory()->create());

        $today = now()->startOfDay();
        $this->seedVisitorFunnel($today, visitors: 3, buyers: 0);

        $this->getJson('/api/v1/admin/dashboard?from='.$today->toDateString().'&to='.$today->toDateString())
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'growth_intelligence' => [
                        'health_status',
                        'health_summary',
                        'growth_comparisons',
                        'alerts',
                    ],
                ],
            ])
            ->assertJsonPath('data.growth_intelligence.health_status', 'healthy');
    }

    private function seedVisitorFunnel(\DateTimeInterface $day, int $visitors, int $buyers): void
    {
        for ($index = 0; $index < $visitors; $index++) {
            $visitor = $this->createVisitor((string) fake()->uuid(), $day);
            $session = $this->createSession($visitor, $day);

            $this->createEvent($visitor, $session, StorefrontEventType::PageView, [
                'path' => '/',
                'created_at' => $day->copy()->addMinutes($index + 1),
            ]);

            if ($index < $buyers) {
                $this->createEvent($visitor, $session, StorefrontEventType::OrderCompleted, [
                    'metadata' => [
                        'order_id' => fake()->uuid(),
                        'order_number' => 'ORD-'.$index,
                        'product_ids' => [],
                    ],
                    'created_at' => $day->copy()->addMinutes($index + 30),
                ]);
            }
        }
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
    ): StorefrontSession {
        return StorefrontSession::query()->create([
            'visitor_id' => $visitor->id,
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
