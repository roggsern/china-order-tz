<?php

namespace App\Services\Storefront;

use App\Enums\StorefrontEventType;
use App\Models\Product;
use App\Models\StorefrontEvent;
use App\Models\StorefrontVisitor;
use App\Services\Reporting\DTOs\ReportPeriod;
use Illuminate\Support\Collection;

class StorefrontAnalyticsService
{
    /**
     * @return array<string, mixed>
     */
    public function traffic(ReportPeriod $period): array
    {
        $referenceDay = $period->to->copy()->startOfDay();
        $snapshot = $this->dailySnapshot($referenceDay);
        $previousSnapshot = $this->dailySnapshot($referenceDay->copy()->subDay());

        return [
            'reference_date' => $referenceDay->toDateString(),
            'visitors_today' => $snapshot['visitors'],
            'sessions_today' => $snapshot['sessions'],
            'new_visitors' => $snapshot['new_visitors'],
            'returning_visitors' => $snapshot['returning_visitors'],
            'growth' => [
                'visitors_change' => $snapshot['visitors'] - $previousSnapshot['visitors'],
                'visitors_change_percent' => $this->percentChange(
                    $previousSnapshot['visitors'],
                    $snapshot['visitors'],
                ),
                'sessions_change' => $snapshot['sessions'] - $previousSnapshot['sessions'],
                'sessions_change_percent' => $this->percentChange(
                    $previousSnapshot['sessions'],
                    $snapshot['sessions'],
                ),
            ],
            'top_pages' => $this->topPages($period),
            'top_products' => $this->topProducts($period),
            'top_searches' => $this->topSearches($period),
        ];
    }

    /**
     * @return array{visitors: int, sessions: int, new_visitors: int, returning_visitors: int}
     */
    public function dailySnapshot(\Carbon\CarbonInterface $day): array
    {
        $start = $day->copy()->startOfDay();
        $end = $day->copy()->endOfDay();

        $visitorIds = StorefrontEvent::query()
            ->whereBetween('created_at', [$start, $end])
            ->distinct()
            ->pluck('visitor_id');

        $visitors = $visitorIds->count();
        $sessions = (int) StorefrontEvent::query()
            ->whereBetween('created_at', [$start, $end])
            ->distinct('session_id')
            ->count('session_id');

        if ($visitors === 0) {
            return [
                'visitors' => 0,
                'sessions' => 0,
                'new_visitors' => 0,
                'returning_visitors' => 0,
            ];
        }

        $newVisitors = (int) StorefrontVisitor::query()
            ->whereIn('id', $visitorIds)
            ->whereBetween('first_seen_at', [$start, $end])
            ->count();

        return [
            'visitors' => $visitors,
            'sessions' => $sessions,
            'new_visitors' => $newVisitors,
            'returning_visitors' => max(0, $visitors - $newVisitors),
        ];
    }

    /**
     * @return list<array{path: string, views: int}>
     */
    public function topPages(ReportPeriod $period, int $limit = 8): array
    {
        return StorefrontEvent::query()
            ->selectRaw('path, COUNT(*) as views')
            ->where('event_type', StorefrontEventType::PageView->value)
            ->whereBetween('created_at', [$period->from, $period->to])
            ->whereNotNull('path')
            ->groupBy('path')
            ->orderByDesc('views')
            ->orderBy('path')
            ->limit($limit)
            ->get()
            ->map(fn ($row) => [
                'path' => (string) $row->path,
                'views' => (int) $row->views,
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array{product_id: string, name: string, views: int}>
     */
    public function topProducts(ReportPeriod $period, int $limit = 8): array
    {
        $rows = StorefrontEvent::query()
            ->selectRaw('product_id, COUNT(*) as views')
            ->where('event_type', StorefrontEventType::ProductViewed->value)
            ->whereBetween('created_at', [$period->from, $period->to])
            ->whereNotNull('product_id')
            ->groupBy('product_id')
            ->orderByDesc('views')
            ->limit($limit)
            ->get();

        if ($rows->isEmpty()) {
            return [];
        }

        $names = Product::query()
            ->whereIn('id', $rows->pluck('product_id'))
            ->pluck('name', 'id');

        return $rows
            ->map(fn ($row) => [
                'product_id' => (string) $row->product_id,
                'name' => (string) ($names[$row->product_id] ?? 'Unknown product'),
                'views' => (int) $row->views,
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array{query: string, count: int}>
     */
    public function topSearches(ReportPeriod $period, int $limit = 8): array
    {
        /** @var Collection<int, StorefrontEvent> $events */
        $events = StorefrontEvent::query()
            ->where('event_type', StorefrontEventType::SearchPerformed->value)
            ->whereBetween('created_at', [$period->from, $period->to])
            ->get(['metadata']);

        $counts = [];

        foreach ($events as $event) {
            $query = strtolower(trim((string) ($event->metadata['query'] ?? '')));
            if ($query === '') {
                continue;
            }

            $counts[$query] = ($counts[$query] ?? 0) + 1;
        }

        arsort($counts);

        return collect($counts)
            ->take($limit)
            ->map(fn (int $count, string $query) => [
                'query' => $query,
                'count' => $count,
            ])
            ->values()
            ->all();
    }

    private function percentChange(int $previous, int $current): float
    {
        if ($previous === 0) {
            return $current === 0 ? 0.0 : 100.0;
        }

        return round((($current - $previous) / $previous) * 100, 1);
    }
}
