<?php

namespace App\Services\Reporting;

use App\Services\Reporting\DTOs\ReportPeriod;
use App\Services\Storefront\StorefrontAnalyticsService;
use App\Services\Storefront\StorefrontConversionAnalyticsService;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * Single source of truth for analytics.
 * Dashboard and report APIs consume this engine only.
 */
class ReportingEngine
{
    public function __construct(
        private readonly MetricsEngine $metrics,
        private readonly ReportGenerator $reports,
        private readonly ExportService $exports,
        private readonly CommandCenterDashboardService $commandCenter,
        private readonly StorefrontAnalyticsService $storefrontAnalytics,
        private readonly StorefrontConversionAnalyticsService $storefrontConversionAnalytics,
        private readonly GrowthInsightService $growthInsights,
    ) {}

    /**
     * @param  array{from?: string|null, to?: string|null}  $filters
     * @return array<string, mixed>
     */
    public function dashboard(array $filters = []): array
    {
        $period = ReportPeriod::fromInput($filters['from'] ?? null, $filters['to'] ?? null, 30);
        /** @var array<string, string> $sectionErrors */
        $sectionErrors = [];

        $dashboard = [
            'period' => [
                'from' => $period->from->toDateString(),
                'to' => $period->to->toDateString(),
            ],
            'sales' => $this->loadSection('sales', $sectionErrors, fn () => $this->metrics->sales($period)),
            'orders' => $this->loadSection('orders', $sectionErrors, fn () => $this->metrics->orders($period)),
            'customers' => $this->loadSection('customers', $sectionErrors, fn () => $this->metrics->customers($period)),
            'promotions' => $this->loadSection('promotions', $sectionErrors, fn () => $this->metrics->promotions($period)),
            'warehouse' => $this->loadSection('warehouse', $sectionErrors, fn () => $this->metrics->warehouse(null)),
            'shipments' => $this->loadSection('shipments', $sectionErrors, fn () => $this->metrics->shipments(null)),
            'returns' => $this->loadSection('returns', $sectionErrors, fn () => $this->metrics->returns($period)),
            'notifications' => $this->loadSection('notifications', $sectionErrors, fn () => $this->metrics->notifications($period)),
            'charts' => $this->loadSection('charts', $sectionErrors, fn () => [
                'daily_revenue' => $this->metrics->dailyRevenue($period),
                'orders_trend' => $this->metrics->ordersTrend($period),
                'payment_status' => $this->metrics->paymentStatusBreakdown($period),
                'warehouse_status' => $this->metrics->warehouseStatusBreakdown(null),
                'shipment_status' => $this->metrics->shipmentStatusBreakdown(null),
                'returns_trend' => $this->metrics->returnsTrend($period),
            ]),
            'top_products' => $this->loadSection('top_products', $sectionErrors, fn () => $this->metrics->topProducts(8, $period)),
            'recent_activity' => $this->loadSection('recent_activity', $sectionErrors, fn () => $this->metrics->recentActivity(12)),
            'storefront_traffic' => $this->loadSection(
                'storefront_traffic',
                $sectionErrors,
                fn () => $this->storefrontAnalytics->traffic($period),
            ),
            'storefront_conversion' => $this->loadSection(
                'storefront_conversion',
                $sectionErrors,
                fn () => $this->storefrontConversionAnalytics->conversion($period),
            ),
            'growth_intelligence' => $this->loadSection(
                'growth_intelligence',
                $sectionErrors,
                fn () => $this->growthInsights->intelligence($period),
            ),
        ];

        $commandCenter = $this->loadSection('command_center', $sectionErrors, fn () => $this->commandCenter->build($period));
        if (is_array($commandCenter)) {
            $dashboard = array_merge($dashboard, $commandCenter);
        }

        if ($sectionErrors !== []) {
            $dashboard['section_errors'] = $sectionErrors;
        }

        return $dashboard;
    }

    /**
     * @param  array<string, string>  $sectionErrors
     */
    private function loadSection(string $key, array &$sectionErrors, callable $loader): mixed
    {
        try {
            return $loader();
        } catch (Throwable $exception) {
            Log::warning('Dashboard section unavailable', [
                'section' => $key,
                'message' => $exception->getMessage(),
            ]);
            $sectionErrors[$key] = 'Unavailable';

            return null;
        }
    }

    /**
     * @param  array{from?: string|null, to?: string|null}  $filters
     * @return array<string, mixed>
     */
    public function report(string $type, array $filters = []): array
    {
        return $this->reports->generate($type, $filters);
    }

    /**
     * @param  array{from?: string|null, to?: string|null}  $filters
     */
    public function export(string $type, string $format, array $filters = []): StreamedResponse
    {
        $report = $this->reports->generate($type, $filters);

        return $this->exports->export($report, $format);
    }

    public function metrics(): MetricsEngine
    {
        return $this->metrics;
    }
}
