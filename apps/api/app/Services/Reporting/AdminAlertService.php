<?php

namespace App\Services\Reporting;

use App\Enums\GrowthInsightSeverity;
use App\Services\Reporting\DTOs\ReportPeriod;

/**
 * Aggregates operational and growth alerts for the admin alert center.
 *
 * Reuses CommandCenterDashboardService (attention_items) and GrowthInsightService
 * — no duplicate reporting logic.
 */
class AdminAlertService
{
    public function __construct(
        private readonly CommandCenterDashboardService $commandCenter,
        private readonly GrowthInsightService $growthInsights,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function alerts(?string $from = null, ?string $to = null): array
    {
        $period = ReportPeriod::fromInput($from, $to, 30);
        $generatedAt = now()->toIso8601String();

        $commandCenter = $this->commandCenter->build($period);
        $growth = $this->growthInsights->intelligence($period);

        $operational = $this->mapOperationalAlerts($commandCenter['attention_items'], $generatedAt);
        $growthAlerts = $this->mapGrowthAlerts($growth['alerts'], $generatedAt);

        $all = array_merge($operational, $growthAlerts);
        usort($all, static function (array $a, array $b): int {
            $severityRank = [
                GrowthInsightSeverity::High->value => 0,
                GrowthInsightSeverity::Medium->value => 1,
                GrowthInsightSeverity::Low->value => 2,
            ];

            $left = $severityRank[$a['severity']] ?? 99;
            $right = $severityRank[$b['severity']] ?? 99;

            return $left <=> $right;
        });

        return [
            'period' => [
                'from' => $period->from->toDateString(),
                'to' => $period->to->toDateString(),
            ],
            'generated_at' => $generatedAt,
            'counts' => [
                'operational' => count($operational),
                'growth' => count($growthAlerts),
                'total' => count($all),
            ],
            'alerts' => $all,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $attentionItems
     * @return list<array<string, string>>
     */
    private function mapOperationalAlerts(array $attentionItems, string $generatedAt): array
    {
        $alerts = [];

        foreach ($attentionItems as $item) {
            $count = (int) ($item['count'] ?? 0);
            if ($count <= 0) {
                continue;
            }

            $alerts[] = [
                'severity' => $this->mapAttentionSeverity((string) ($item['severity'] ?? 'normal')),
                'title' => (string) ($item['label'] ?? 'Operational alert'),
                'message' => sprintf('%d item(s) need attention.', $count),
                'source' => 'operational',
                'created_at' => $generatedAt,
                'key' => (string) ($item['key'] ?? ''),
                'href' => (string) ($item['href'] ?? '/admin'),
            ];
        }

        return $alerts;
    }

    /**
     * @param  list<array<string, string>>  $growthAlerts
     * @return list<array<string, string>>
     */
    private function mapGrowthAlerts(array $growthAlerts, string $generatedAt): array
    {
        return array_map(static function (array $alert) use ($generatedAt): array {
            return [
                'severity' => (string) ($alert['severity'] ?? GrowthInsightSeverity::Low->value),
                'title' => (string) ($alert['title'] ?? 'Growth alert'),
                'message' => (string) ($alert['message'] ?? ''),
                'source' => 'growth',
                'created_at' => $generatedAt,
                'type' => (string) ($alert['type'] ?? ''),
                'category' => (string) ($alert['category'] ?? ''),
            ];
        }, $growthAlerts);
    }

    private function mapAttentionSeverity(string $severity): string
    {
        return match ($severity) {
            'high' => GrowthInsightSeverity::High->value,
            'medium' => GrowthInsightSeverity::Medium->value,
            default => GrowthInsightSeverity::Low->value,
        };
    }
}
