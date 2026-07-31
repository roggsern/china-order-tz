<?php

namespace App\Services\ConfigurationHealth\Checks;

use App\Models\Review;
use App\Models\WishlistItem;
use App\Services\ConfigurationHealth\Concerns\BuildsHealthCheckResult;
use App\Services\ConfigurationHealth\Contracts\ConfigurationHealthCheck;
use App\Services\Features\FeatureAvailabilityService;
use App\Services\Features\FeatureFlagResolver;
use App\Services\Features\MaintenanceModeResolver;
use Throwable;

final class FeatureHealthCheck implements ConfigurationHealthCheck
{
    use BuildsHealthCheckResult;

    public function __construct(
        private readonly FeatureFlagResolver $flags,
        private readonly MaintenanceModeResolver $maintenance,
        private readonly FeatureAvailabilityService $availability,
    ) {}

    public function run(): array
    {
        try {
            $results = [];

            $results[] = $this->result(
                'features',
                'healthy',
                'Feature runtime connected via FeatureAvailabilityService.',
            );

            if ($this->maintenance->isEnabled()) {
                $message = $this->maintenance->message() === ''
                    ? 'Maintenance mode is enabled and no maintenance message is set.'
                    : 'Maintenance mode is enabled.';
                $results[] = $this->result('features', 'warning', $message);
            }

            $enabled = $this->flags->enabledFeatures();
            foreach ($enabled as $flag) {
                $results[] = $this->result(
                    'features',
                    'info',
                    sprintf('Optional feature flag enabled: %s (runtime enforced).', $flag),
                );

                $usage = match ($flag) {
                    FeatureAvailabilityService::WISHLIST => WishlistItem::query()->count(),
                    FeatureAvailabilityService::REVIEWS => Review::query()->count(),
                    default => null,
                };

                if ($usage === 0) {
                    $results[] = $this->result(
                        'features',
                        'info',
                        sprintf('Feature "%s" is enabled but has no recorded usage yet.', $flag),
                    );
                }
            }

            if ($enabled === [] && ! $this->maintenance->isEnabled()) {
                $results[] = $this->result(
                    'features',
                    'healthy',
                    'Optional feature flags are at defaults (all disabled).',
                );
            }

            // Sanity: public flags must never expose forbidden keys.
            $public = $this->availability->publicFlags();
            if (array_keys($public) !== FeatureFlagResolver::ALLOWED_FLAGS) {
                $results[] = $this->result(
                    'features',
                    'warning',
                    'Public feature payload keys do not match allowed runtime flags.',
                );
            }

            return $results;
        } catch (Throwable) {
            return [
                $this->result('features', 'critical', 'Unable to resolve feature configuration.'),
            ];
        }
    }
}
