<?php

namespace App\Services\ConfigurationHealth\Checks;

use App\Models\Store;
use App\Services\ConfigurationHealth\Concerns\BuildsHealthCheckResult;
use App\Services\ConfigurationHealth\Contracts\ConfigurationHealthCheck;
use App\Services\Stores\StoreSettingsResolver;
use Throwable;

final class StoreHealthCheck implements ConfigurationHealthCheck
{
    use BuildsHealthCheckResult;

    public function __construct(
        private readonly StoreSettingsResolver $storeSettings,
    ) {}

    public function run(): array
    {
        try {
            $stores = Store::query()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->limit(25)
                ->get();

            if ($stores->isEmpty()) {
                return [
                    $this->result('store', 'critical', 'No active stores are configured.'),
                ];
            }

            $results = [];
            $incompleteBusiness = 0;
            $incompleteCustomer = 0;

            foreach ($stores as $store) {
                $sections = $this->storeSettings->resolveSections($store);
                $business = $sections['business'];
                $customer = $sections['customer'];

                $businessBlank = trim((string) ($business['display_name'] ?? '')) === ''
                    && trim((string) ($business['phone'] ?? '')) === ''
                    && trim((string) ($business['email'] ?? '')) === '';
                if ($businessBlank) {
                    $incompleteBusiness++;
                }

                $customerBlank = trim((string) ($customer['support_phone'] ?? '')) === ''
                    && trim((string) ($customer['support_email'] ?? '')) === '';
                if ($customerBlank) {
                    $incompleteCustomer++;
                }
            }

            if ($incompleteBusiness > 0) {
                $results[] = $this->result(
                    'store',
                    'warning',
                    "{$incompleteBusiness} active store(s) are missing business contact details.",
                );
            }

            if ($incompleteCustomer > 0) {
                $results[] = $this->result(
                    'store',
                    'info',
                    "{$incompleteCustomer} active store(s) are missing customer support contacts.",
                );
            }

            if ($results === []) {
                $results[] = $this->result(
                    'store',
                    'healthy',
                    'Active store business settings look complete.',
                );
            }

            return $results;
        } catch (Throwable) {
            return [
                $this->result('store', 'critical', 'Unable to resolve store configuration.'),
            ];
        }
    }
}
