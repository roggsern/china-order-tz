<?php

namespace App\Services\Storefront;

use App\Enums\StorefrontEventType;
use App\Models\Order;
use App\Models\Product;
use App\Models\StorefrontEvent;
use App\Services\Reporting\DTOs\ReportPeriod;
use Illuminate\Support\Collection;

class StorefrontConversionAnalyticsService
{
    /**
     * @return array<string, mixed>
     */
    public function conversion(ReportPeriod $period): array
    {
        $funnel = $this->funnel($period);

        return [
            'funnel' => $funnel,
            'conversion_rates' => $this->conversionRates($funnel),
            'attribution' => $this->attribution($period),
            'product_insights' => $this->productInsights($period),
        ];
    }

    /**
     * @return array{
     *     visitors: int,
     *     product_viewers: int,
     *     cart_users: int,
     *     checkout_users: int,
     *     buyers: int
     * }
     */
    public function funnel(ReportPeriod $period): array
    {
        return [
            'visitors' => $this->distinctVisitors($period),
            'product_viewers' => $this->distinctVisitorsForEvent($period, StorefrontEventType::ProductViewed),
            'cart_users' => $this->distinctVisitorsForEvent($period, StorefrontEventType::AddToCart),
            'checkout_users' => $this->distinctVisitorsForEvent($period, StorefrontEventType::CheckoutStarted),
            'buyers' => $this->distinctVisitorsForEvent($period, StorefrontEventType::OrderCompleted),
        ];
    }

    /**
     * @param  array{
     *     visitors: int,
     *     product_viewers: int,
     *     cart_users: int,
     *     checkout_users: int,
     *     buyers: int
     * }  $funnel
     * @return array<string, float>
     */
    public function conversionRates(array $funnel): array
    {
        return [
            'visitor_to_product_view' => $this->rate($funnel['product_viewers'], $funnel['visitors']),
            'product_view_to_cart' => $this->rate($funnel['cart_users'], $funnel['product_viewers']),
            'cart_to_checkout' => $this->rate($funnel['checkout_users'], $funnel['cart_users']),
            'checkout_to_purchase' => $this->rate($funnel['buyers'], $funnel['checkout_users']),
            'visitor_to_purchase' => $this->rate($funnel['buyers'], $funnel['visitors']),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function attribution(ReportPeriod $period): array
    {
        $ordersWithJourney = (int) Order::query()
            ->whereNotNull('storefront_visitor_id')
            ->whereNotNull('storefront_session_id')
            ->whereBetween('paid_at', [$period->from, $period->to])
            ->count();

        $attributedBuyers = $this->distinctVisitorsForEvent($period, StorefrontEventType::OrderCompleted);

        return [
            'orders_with_journey' => $ordersWithJourney,
            'attributed_buyers' => $attributedBuyers,
            'first_touch_pages' => $this->firstTouchPages($period),
        ];
    }

    /**
     * @return list<array{path: string, visitors: int, orders: int}>
     */
    public function firstTouchPages(ReportPeriod $period, int $limit = 6): array
    {
        /** @var Collection<int, StorefrontEvent> $completedEvents */
        $completedEvents = StorefrontEvent::query()
            ->where('event_type', StorefrontEventType::OrderCompleted->value)
            ->whereBetween('created_at', [$period->from, $period->to])
            ->get(['visitor_id', 'metadata']);

        if ($completedEvents->isEmpty()) {
            return [];
        }

        $buyerVisitorIds = $completedEvents->pluck('visitor_id')->unique()->values();
        $ordersByVisitor = [];

        foreach ($completedEvents as $event) {
            $ordersByVisitor[$event->visitor_id] = ($ordersByVisitor[$event->visitor_id] ?? 0) + 1;
        }

        /** @var Collection<int, StorefrontEvent> $firstTouches */
        $firstTouches = StorefrontEvent::query()
            ->where('event_type', StorefrontEventType::PageView->value)
            ->whereIn('visitor_id', $buyerVisitorIds)
            ->whereBetween('created_at', [$period->from, $period->to])
            ->whereNotNull('path')
            ->orderBy('created_at')
            ->get(['visitor_id', 'path']);

        $visitorFirstPath = [];
        foreach ($firstTouches as $touch) {
            if (! isset($visitorFirstPath[$touch->visitor_id])) {
                $visitorFirstPath[$touch->visitor_id] = (string) $touch->path;
            }
        }

        $aggregates = [];
        foreach ($visitorFirstPath as $visitorId => $path) {
            if (! isset($aggregates[$path])) {
                $aggregates[$path] = ['visitors' => 0, 'orders' => 0];
            }

            $aggregates[$path]['visitors'] += 1;
            $aggregates[$path]['orders'] += $ordersByVisitor[$visitorId] ?? 0;
        }

        uasort($aggregates, static fn (array $a, array $b) => $b['orders'] <=> $a['orders'] ?: $b['visitors'] <=> $a['visitors']);

        return collect($aggregates)
            ->take($limit)
            ->map(fn (array $row, string $path) => [
                'path' => $path,
                'visitors' => $row['visitors'],
                'orders' => $row['orders'],
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array{
     *     product_id: string,
     *     name: string,
     *     views: int,
     *     cart_additions: int,
     *     orders: int,
     *     conversion_rate: float
     * }>
     */
    public function productInsights(ReportPeriod $period, int $limit = 8): array
    {
        $views = $this->productEventCounts($period, StorefrontEventType::ProductViewed);
        $cartAdds = $this->productEventCounts($period, StorefrontEventType::AddToCart);
        $orders = $this->productOrderCounts($period);

        $productIds = collect($views)
            ->keys()
            ->merge($cartAdds->keys())
            ->merge($orders->keys())
            ->unique()
            ->values();

        if ($productIds->isEmpty()) {
            return [];
        }

        $names = Product::query()
            ->whereIn('id', $productIds)
            ->pluck('name', 'id');

        return $productIds
            ->map(function (string $productId) use ($views, $cartAdds, $orders, $names): array {
                $viewCount = (int) ($views[$productId] ?? 0);
                $orderCount = (int) ($orders[$productId] ?? 0);

                return [
                    'product_id' => $productId,
                    'name' => (string) ($names[$productId] ?? 'Unknown product'),
                    'views' => $viewCount,
                    'cart_additions' => (int) ($cartAdds[$productId] ?? 0),
                    'orders' => $orderCount,
                    'conversion_rate' => $this->rate($orderCount, $viewCount),
                ];
            })
            ->sortByDesc(fn (array $row) => [$row['orders'], $row['views'], $row['cart_additions']])
            ->take($limit)
            ->values()
            ->all();
    }

    private function distinctVisitors(ReportPeriod $period): int
    {
        return (int) StorefrontEvent::query()
            ->whereBetween('created_at', [$period->from, $period->to])
            ->distinct('visitor_id')
            ->count('visitor_id');
    }

    private function distinctVisitorsForEvent(ReportPeriod $period, StorefrontEventType $type): int
    {
        return (int) StorefrontEvent::query()
            ->where('event_type', $type->value)
            ->whereBetween('created_at', [$period->from, $period->to])
            ->distinct('visitor_id')
            ->count('visitor_id');
    }

    /**
     * @return Collection<string, int>
     */
    private function productEventCounts(ReportPeriod $period, StorefrontEventType $type): Collection
    {
        return StorefrontEvent::query()
            ->selectRaw('product_id, COUNT(*) as total')
            ->where('event_type', $type->value)
            ->whereBetween('created_at', [$period->from, $period->to])
            ->whereNotNull('product_id')
            ->groupBy('product_id')
            ->pluck('total', 'product_id')
            ->map(fn ($count) => (int) $count);
    }

    /**
     * @return Collection<string, int>
     */
    private function productOrderCounts(ReportPeriod $period): Collection
    {
        /** @var Collection<int, StorefrontEvent> $events */
        $events = StorefrontEvent::query()
            ->where('event_type', StorefrontEventType::OrderCompleted->value)
            ->whereBetween('created_at', [$period->from, $period->to])
            ->get(['metadata']);

        $counts = collect();

        foreach ($events as $event) {
            $productIds = $event->metadata['product_ids'] ?? [];
            if (! is_array($productIds)) {
                continue;
            }

            foreach ($productIds as $productId) {
                if (! is_string($productId) || trim($productId) === '') {
                    continue;
                }

                $counts[$productId] = ($counts[$productId] ?? 0) + 1;
            }
        }

        return $counts;
    }

    private function rate(int $numerator, int $denominator): float
    {
        if ($denominator === 0) {
            return 0.0;
        }

        return round(($numerator / $denominator) * 100, 1);
    }
}
