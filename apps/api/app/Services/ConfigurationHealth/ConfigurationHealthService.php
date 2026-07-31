<?php

namespace App\Services\ConfigurationHealth;

use App\Services\ConfigurationHealth\Checks\FeatureHealthCheck;
use App\Services\ConfigurationHealth\Checks\NotificationHealthCheck;
use App\Services\ConfigurationHealth\Checks\PaymentHealthCheck;
use App\Services\ConfigurationHealth\Checks\SecurityHealthCheck;
use App\Services\ConfigurationHealth\Checks\ShippingHealthCheck;
use App\Services\ConfigurationHealth\Checks\StoreHealthCheck;
use App\Services\ConfigurationHealth\Contracts\ConfigurationHealthCheck;

/**
 * Read-only configuration health aggregator.
 * Uses existing settings/domain resolvers — does not modify business engines or store secrets.
 */
final class ConfigurationHealthService
{
    /** @var list<ConfigurationHealthCheck> */
    private array $checks;

    public function __construct(
        PaymentHealthCheck $payments,
        ShippingHealthCheck $shipping,
        NotificationHealthCheck $notifications,
        StoreHealthCheck $stores,
        FeatureHealthCheck $features,
        SecurityHealthCheck $security,
    ) {
        $this->checks = [
            $payments,
            $shipping,
            $notifications,
            $stores,
            $features,
            $security,
        ];
    }

    /**
     * @return array{
     *   overall_score: int,
     *   status: string,
     *   checks: list<array{group: string, status: string, message: string, severity: string}>,
     *   summary: array{critical_count: int, warning_count: int, info_count: int, healthy_count: int}
     * }
     */
    public function report(): array
    {
        $checks = [];
        foreach ($this->checks as $check) {
            foreach ($check->run() as $result) {
                $checks[] = $result;
            }
        }

        $critical = 0;
        $warning = 0;
        $info = 0;
        $healthy = 0;

        foreach ($checks as $check) {
            match ($check['status']) {
                'critical' => $critical++,
                'warning' => $warning++,
                'healthy' => $healthy++,
                default => $info++,
            };
        }

        $score = $this->computeScore($critical, $warning, $info);
        $status = $critical > 0 ? 'critical' : ($warning > 0 ? 'warning' : 'healthy');

        return [
            'overall_score' => $score,
            'status' => $status,
            'checks' => $checks,
            'summary' => [
                'critical_count' => $critical,
                'warning_count' => $warning,
                'info_count' => $info,
                'healthy_count' => $healthy,
            ],
        ];
    }

    private function computeScore(int $criticalCount, int $warningCount, int $infoCount): int
    {
        $score = 100;
        $score -= min(70, $criticalCount * 15);
        $score -= min(25, $warningCount * 5);
        $score -= min(5, $infoCount * 1);

        return max(0, $score);
    }
}
