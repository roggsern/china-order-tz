<?php

namespace App\Services\Reporting;

use App\Services\Reporting\DTOs\ReportPeriod;
use Symfony\Component\HttpFoundation\StreamedResponse;

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
    ) {}

    /**
     * @param  array{from?: string|null, to?: string|null}  $filters
     * @return array<string, mixed>
     */
    public function dashboard(array $filters = []): array
    {
        $period = ReportPeriod::fromInput($filters['from'] ?? null, $filters['to'] ?? null, 30);

        return [
            'period' => [
                'from' => $period->from->toDateString(),
                'to' => $period->to->toDateString(),
            ],
            'sales' => $this->metrics->sales($period),
            'orders' => $this->metrics->orders($period),
            'customers' => $this->metrics->customers($period),
            'promotions' => $this->metrics->promotions($period),
            'warehouse' => $this->metrics->warehouse(null),
            'shipments' => $this->metrics->shipments(null),
            'returns' => $this->metrics->returns($period),
            'notifications' => $this->metrics->notifications($period),
            'charts' => [
                'daily_revenue' => $this->metrics->dailyRevenue($period),
                'orders_trend' => $this->metrics->ordersTrend($period),
                'payment_status' => $this->metrics->paymentStatusBreakdown($period),
                'warehouse_status' => $this->metrics->warehouseStatusBreakdown(null),
                'shipment_status' => $this->metrics->shipmentStatusBreakdown(null),
                'returns_trend' => $this->metrics->returnsTrend($period),
            ],
            'top_products' => $this->metrics->topProducts(8, $period),
            'recent_activity' => $this->metrics->recentActivity(12),
        ];
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
