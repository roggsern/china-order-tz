<?php

namespace App\Services\Reporting;

use App\Enums\GrowthInsightSeverity;
use App\Models\Product;
use App\Services\Reporting\DTOs\ReportPeriod;
use App\Services\Storefront\StorefrontAnalyticsService;
use App\Services\Storefront\StorefrontConversionAnalyticsService;

class GrowthInsightService
{
    public function __construct(
        private readonly StorefrontAnalyticsService $trafficAnalytics,
        private readonly StorefrontConversionAnalyticsService $conversionAnalytics,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function intelligence(ReportPeriod $period): array
    {
        $currentTraffic = $this->trafficAnalytics->traffic($period);
        $previousPeriod = $this->previousPeriod($period);

        $currentConversion = $this->conversionAnalytics->conversion($period);
        $previousConversion = $this->conversionAnalytics->conversion($previousPeriod);

        $growthComparisons = $this->growthComparisons(
            $currentConversion,
            $previousConversion,
        );

        $alerts = array_merge(
            $this->trafficUpConversionDownAlerts($growthComparisons),
            $this->conversionDropAlerts($growthComparisons, $currentConversion, $previousConversion),
            $this->highViewsLowOrdersAlerts($currentConversion['product_insights']),
            $this->searchDemandGapAlerts($currentTraffic['top_searches']),
            $this->opportunityAlerts($growthComparisons, $currentConversion),
        );

        usort($alerts, static function (array $a, array $b): int {
            $severityRank = [
                GrowthInsightSeverity::High->value => 0,
                GrowthInsightSeverity::Medium->value => 1,
                GrowthInsightSeverity::Low->value => 2,
            ];

            $left = $severityRank[$a['severity']] ?? 99;
            $right = $severityRank[$b['severity']] ?? 99;

            return $left <=> $right;
        });

        $healthSummary = $this->healthSummary($currentConversion, $growthComparisons, $alerts);

        return [
            'health_status' => $this->resolveHealthStatus($alerts),
            'health_summary' => $healthSummary,
            'growth_comparisons' => $growthComparisons,
            'alerts' => $alerts,
        ];
    }

    /**
     * @param  array<string, mixed>  $currentConversion
     * @param  array<string, mixed>  $previousConversion
     * @return array<string, float|int>
     */
    private function growthComparisons(
        array $currentConversion,
        array $previousConversion,
    ): array {
        $currentVisitors = (int) $currentConversion['funnel']['visitors'];
        $previousVisitors = (int) $previousConversion['funnel']['visitors'];
        $currentBuyers = (int) $currentConversion['funnel']['buyers'];
        $previousBuyers = (int) $previousConversion['funnel']['buyers'];

        $currentRate = (float) $currentConversion['conversion_rates']['visitor_to_purchase'];
        $previousRate = (float) $previousConversion['conversion_rates']['visitor_to_purchase'];

        return [
            'visitors_current' => $currentVisitors,
            'visitors_previous' => $previousVisitors,
            'visitors_change_percent' => $this->percentChange($previousVisitors, $currentVisitors),
            'buyers_current' => $currentBuyers,
            'buyers_previous' => $previousBuyers,
            'buyers_change_percent' => $this->percentChange($previousBuyers, $currentBuyers),
            'conversion_rate_current' => $currentRate,
            'conversion_rate_previous' => $previousRate,
            'conversion_change_points' => round($currentRate - $previousRate, 1),
        ];
    }

    /**
     * @param  array<string, float|int>  $growth
     * @return list<array<string, string>>
     */
    private function trafficUpConversionDownAlerts(array $growth): array
    {
        $trafficUp = (float) $growth['visitors_change_percent'] >= 10.0;
        $conversionDown = (float) $growth['conversion_change_points'] <= -2.0;

        if (! $trafficUp || ! $conversionDown) {
            return [];
        }

        $severity = (float) $growth['visitors_change_percent'] >= 25.0
            && (float) $growth['conversion_change_points'] <= -5.0
            ? GrowthInsightSeverity::High
            : GrowthInsightSeverity::Medium;

        return [[
            'type' => 'traffic_up_conversion_down',
            'category' => 'warning',
            'severity' => $severity->value,
            'title' => 'Traffic is up but conversion is down',
            'message' => sprintf(
                'Visitors rose by %.1f%% while visitor-to-purchase fell %.1f points (%.1f%% → %.1f%%). Review checkout friction or product-market fit.',
                (float) $growth['visitors_change_percent'],
                abs((float) $growth['conversion_change_points']),
                (float) $growth['conversion_rate_previous'],
                (float) $growth['conversion_rate_current'],
            ),
        ]];
    }

    /**
     * @param  array<string, float|int>  $growth
     * @param  array<string, mixed>  $currentConversion
     * @param  array<string, mixed>  $previousConversion
     * @return list<array<string, string>>
     */
    private function conversionDropAlerts(
        array $growth,
        array $currentConversion,
        array $previousConversion,
    ): array {
        if ((float) $growth['visitors_change_percent'] >= 10.0) {
            return [];
        }

        $currentVisitors = (int) $currentConversion['funnel']['visitors'];
        $previousVisitors = (int) $previousConversion['funnel']['visitors'];
        $changePoints = (float) $growth['conversion_change_points'];

        if ($currentVisitors < 5 || $previousVisitors < 5 || $changePoints > -3.0) {
            return [];
        }

        $severity = $changePoints <= -5.0
            ? GrowthInsightSeverity::High
            : GrowthInsightSeverity::Medium;

        return [[
            'type' => 'conversion_drop',
            'category' => 'warning',
            'severity' => $severity->value,
            'title' => 'Storefront conversion dropped',
            'message' => sprintf(
                'Visitor-to-purchase fell %.1f points from %.1f%% to %.1f%% in the selected period.',
                abs($changePoints),
                (float) $growth['conversion_rate_previous'],
                (float) $growth['conversion_rate_current'],
            ),
        ]];
    }

    /**
     * @param  list<array{
     *     product_id: string,
     *     name: string,
     *     views: int,
     *     cart_additions: int,
     *     orders: int,
     *     conversion_rate: float
     * }>  $productInsights
     * @return list<array<string, string>>
     */
    private function highViewsLowOrdersAlerts(array $productInsights): array
    {
        $alerts = [];

        foreach ($productInsights as $product) {
            if ($product['views'] < 5 || $product['conversion_rate'] > 2.0 || $product['orders'] > 1) {
                continue;
            }

            $severity = $product['views'] >= 20 && $product['orders'] === 0
                ? GrowthInsightSeverity::High
                : GrowthInsightSeverity::Medium;

            $alerts[] = [
                'type' => 'high_views_low_orders',
                'category' => 'warning',
                'severity' => $severity->value,
                'title' => 'High interest, low conversion',
                'message' => sprintf(
                    '%s has %d views but only %d orders (%.1f%% conversion). Check pricing, stock, or product page content.',
                    $product['name'],
                    $product['views'],
                    $product['orders'],
                    $product['conversion_rate'],
                ),
            ];
        }

        return $alerts;
    }

    /**
     * @param  list<array{query: string, count: int}>  $topSearches
     * @return list<array<string, string>>
     */
    private function searchDemandGapAlerts(array $topSearches): array
    {
        $alerts = [];

        foreach ($topSearches as $search) {
            if ($search['count'] < 3) {
                continue;
            }

            if ($this->searchMatchesCatalog($search['query'])) {
                continue;
            }

            $severity = $search['count'] >= 8
                ? GrowthInsightSeverity::High
                : GrowthInsightSeverity::Medium;

            $alerts[] = [
                'type' => 'search_demand_gap',
                'category' => 'warning',
                'severity' => $severity->value,
                'title' => 'Unmet search demand',
                'message' => sprintf(
                    'Customers searched for "%s" %d times without a clear catalog match. Consider adding or surfacing relevant products.',
                    $search['query'],
                    $search['count'],
                ),
            ];
        }

        return $alerts;
    }

    /**
     * @param  array<string, float|int>  $growth
     * @param  array<string, mixed>  $currentConversion
     * @return list<array<string, string>>
     */
    private function opportunityAlerts(array $growth, array $currentConversion): array
    {
        $alerts = [];

        if ((int) $growth['visitors_current'] >= 5 && (float) $growth['conversion_change_points'] >= 2.0) {
            $alerts[] = [
                'type' => 'conversion_improvement',
                'category' => 'opportunity',
                'severity' => GrowthInsightSeverity::Low->value,
                'title' => 'Conversion is improving',
                'message' => sprintf(
                    'Visitor-to-purchase improved by %.1f points to %.1f%%. Double down on current merchandising and checkout flow.',
                    (float) $growth['conversion_change_points'],
                    (float) $growth['conversion_rate_current'],
                ),
            ];
        }

        foreach ($currentConversion['product_insights'] as $product) {
            if ($product['views'] < 5 || $product['conversion_rate'] < 10.0) {
                continue;
            }

            $alerts[] = [
                'type' => 'strong_product_conversion',
                'category' => 'opportunity',
                'severity' => GrowthInsightSeverity::Low->value,
                'title' => 'Strong product performer',
                'message' => sprintf(
                    '%s converts at %.1f%% from %d views (%d orders). Consider featuring it in campaigns.',
                    $product['name'],
                    $product['conversion_rate'],
                    $product['views'],
                    $product['orders'],
                ),
            ];
        }

        return $alerts;
    }

    /**
     * @param  list<array<string, string>>  $alerts
     * @param  array<string, float|int>  $growth
     * @param  array<string, mixed>  $currentConversion
     * @return array<string, int|float>
     */
    private function healthSummary(array $currentConversion, array $growth, array $alerts): array
    {
        $warningCount = count(array_filter($alerts, static fn (array $alert) => $alert['category'] === 'warning'));
        $opportunityCount = count(array_filter($alerts, static fn (array $alert) => $alert['category'] === 'opportunity'));
        $highCount = count(array_filter($alerts, static fn (array $alert) => $alert['severity'] === GrowthInsightSeverity::High->value));

        return [
            'visitors' => (int) $currentConversion['funnel']['visitors'],
            'buyers' => (int) $currentConversion['funnel']['buyers'],
            'visitor_to_purchase' => (float) $currentConversion['conversion_rates']['visitor_to_purchase'],
            'visitors_change_percent' => (float) $growth['visitors_change_percent'],
            'conversion_change_points' => (float) $growth['conversion_change_points'],
            'warning_count' => $warningCount,
            'opportunity_count' => $opportunityCount,
            'high_severity_count' => $highCount,
        ];
    }

    /**
     * @param  list<array<string, string>>  $alerts
     */
    private function resolveHealthStatus(array $alerts): string
    {
        $hasHighWarning = collect($alerts)->contains(
            static fn (array $alert) => $alert['category'] === 'warning'
                && $alert['severity'] === GrowthInsightSeverity::High->value,
        );

        if ($hasHighWarning) {
            return 'at_risk';
        }

        $hasWarning = collect($alerts)->contains(
            static fn (array $alert) => $alert['category'] === 'warning',
        );

        if ($hasWarning) {
            return 'watch';
        }

        return 'healthy';
    }

    private function previousPeriod(ReportPeriod $period): ReportPeriod
    {
        $days = max(1, $period->from->diffInDays($period->to) + 1);
        $previousEnd = $period->from->copy()->subDay()->endOfDay();
        $previousStart = $previousEnd->copy()->subDays($days - 1)->startOfDay();

        return new ReportPeriod($previousStart, $previousEnd);
    }

    private function searchMatchesCatalog(string $query): bool
    {
        $normalized = trim(strtolower($query));
        if ($normalized === '') {
            return true;
        }

        return Product::query()
            ->where(function ($builder) use ($normalized): void {
                $builder->whereRaw('LOWER(name) LIKE ?', ['%'.$normalized.'%'])
                    ->orWhereRaw('LOWER(slug) LIKE ?', ['%'.str_replace(' ', '-', $normalized).'%']);
            })
            ->exists();
    }

    private function percentChange(int $previous, int $current): float
    {
        if ($previous === 0) {
            return $current === 0 ? 0.0 : 100.0;
        }

        return round((($current - $previous) / $previous) * 100, 1);
    }
}
