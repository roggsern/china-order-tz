<?php

namespace App\Services\ConfigurationHealth\Checks;

use App\Services\ConfigurationHealth\Concerns\BuildsHealthCheckResult;
use App\Services\ConfigurationHealth\Contracts\ConfigurationHealthCheck;
use App\Services\Payments\PaymentConfigurationResolver;
use Throwable;

final class PaymentHealthCheck implements ConfigurationHealthCheck
{
    use BuildsHealthCheckResult;

    public function __construct(
        private readonly PaymentConfigurationResolver $payments,
    ) {}

    public function run(): array
    {
        try {
            $enabled = $this->payments->enabledMethodList();
            $default = $this->payments->resolveDefaultProvider();
            $results = [];

            if ($enabled === []) {
                $results[] = $this->result(
                    'payments',
                    'critical',
                    'No payment methods are enabled.',
                );
            }

            if (! $this->payments->isMethodEnabled($default)) {
                $results[] = $this->result(
                    'payments',
                    'critical',
                    "Default provider [{$default}] is disabled.",
                );
            } elseif (! $this->payments->isProviderAvailable($default) && $default === 'nmb') {
                $results[] = $this->result(
                    'payments',
                    'critical',
                    'Default provider NMB is enabled but not available from ENV configuration.',
                );
            }

            foreach ($enabled as $method) {
                if ($method === $default) {
                    continue;
                }
                if (! $this->payments->isProviderAvailable($method) && in_array($method, ['nmb', 'mpesa', 'card'], true)) {
                    $results[] = $this->result(
                        'payments',
                        'warning',
                        "Enabled method [{$method}] is not provider-ready (ENV).",
                    );
                }
            }

            if ($results === []) {
                $results[] = $this->result(
                    'payments',
                    'healthy',
                    "Payment configuration looks healthy (default: {$default}).",
                );
            }

            return $results;
        } catch (Throwable) {
            return [
                $this->result('payments', 'critical', 'Unable to resolve payment configuration.'),
            ];
        }
    }
}
