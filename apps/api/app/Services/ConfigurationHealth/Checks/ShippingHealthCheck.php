<?php

namespace App\Services\ConfigurationHealth\Checks;

use App\Services\ConfigurationHealth\Concerns\BuildsHealthCheckResult;
use App\Services\ConfigurationHealth\Contracts\ConfigurationHealthCheck;
use App\Services\Shipping\ShippingDurationResolver;
use App\Services\Shipping\ShippingRateService;
use Throwable;

final class ShippingHealthCheck implements ConfigurationHealthCheck
{
    use BuildsHealthCheckResult;

    public function __construct(
        private readonly ShippingRateService $rates,
        private readonly ShippingDurationResolver $durations,
    ) {}

    public function run(): array
    {
        try {
            $rows = $this->rates->listRates();
            $results = [];

            if ($rows === []) {
                return [
                    $this->result('shipping', 'critical', 'No managed shipping rates are configured.'),
                ];
            }

            $activeCount = 0;
            foreach ($rows as $row) {
                $method = (string) ($row['method'] ?? 'unknown');
                $active = (bool) ($row['active'] ?? false);
                if ($active) {
                    $activeCount++;
                } else {
                    $results[] = $this->result(
                        'shipping',
                        'warning',
                        "Shipping method [{$method}] is inactive.",
                    );
                }

                $min = $row['estimated_min_days'] ?? null;
                $max = $row['estimated_max_days'] ?? null;
                $typical = $row['estimated_delivery_days'] ?? null;
                if ($min === null || $max === null || $typical === null) {
                    $results[] = $this->result(
                        'shipping',
                        'warning',
                        "Shipping method [{$method}] is missing duration window fields.",
                    );
                } elseif (! ((int) $min <= (int) $typical && (int) $typical <= (int) $max)) {
                    $results[] = $this->result(
                        'shipping',
                        'critical',
                        "Shipping method [{$method}] has an invalid duration window.",
                    );
                }
            }

            if ($activeCount === 0) {
                $results[] = $this->result(
                    'shipping',
                    'critical',
                    'No active shipping rates are available.',
                );
            }

            $resolved = $this->durations->resolveAll();
            foreach (['air', 'sea', 'local'] as $mode) {
                $source = $resolved[$mode]['source'] ?? 'fallback';
                if ($source === 'fallback') {
                    $results[] = $this->result(
                        'shipping',
                        'warning',
                        "Duration resolver is using fallback for [{$mode}].",
                    );
                }
            }

            if ($results === []) {
                $results[] = $this->result(
                    'shipping',
                    'healthy',
                    'Shipping rates and duration windows look healthy.',
                );
            }

            return $results;
        } catch (Throwable) {
            return [
                $this->result('shipping', 'critical', 'Unable to resolve shipping configuration.'),
            ];
        }
    }
}
