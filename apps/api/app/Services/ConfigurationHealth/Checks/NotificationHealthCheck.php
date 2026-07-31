<?php

namespace App\Services\ConfigurationHealth\Checks;

use App\Services\ConfigurationHealth\Concerns\BuildsHealthCheckResult;
use App\Services\ConfigurationHealth\Contracts\ConfigurationHealthCheck;
use App\Services\Notifications\NotificationConfigurationResolver;
use Throwable;

final class NotificationHealthCheck implements ConfigurationHealthCheck
{
    use BuildsHealthCheckResult;

    public function __construct(
        private readonly NotificationConfigurationResolver $notifications,
    ) {}

    public function run(): array
    {
        try {
            $results = [];
            $config = $this->notifications->presentConfig();
            $channels = $config['channels'] ?? [];
            $providerStatus = $config['provider_status'] ?? [];

            if (! ($channels['in_app_enabled'] ?? false)) {
                $results[] = $this->result(
                    'notifications',
                    'critical',
                    'In-app notifications are disabled; customers may miss order updates.',
                );
            }

            foreach (['email', 'sms', 'whatsapp', 'push'] as $channel) {
                $enabledKey = $channel.'_enabled';
                if (! ($channels[$enabledKey] ?? false)) {
                    continue;
                }
                $configured = (bool) ($providerStatus[$channel]['configured'] ?? false);
                if (! $configured) {
                    $results[] = $this->result(
                        'notifications',
                        'warning',
                        ucfirst($channel).' channel is enabled but provider is not configured in ENV.',
                    );
                }
            }

            foreach ($this->notifications->eventChannelMap() as $event => $mappedChannels) {
                $deliverable = $this->notifications->filterForDelivery($mappedChannels);
                if ($deliverable === []) {
                    $results[] = $this->result(
                        'notifications',
                        'critical',
                        "Event [{$event}] has no deliverable channels after enablement/provider checks.",
                    );
                }
            }

            // Password recovery expects email in production; warn when provider ENV is missing.
            $emailConfigured = (bool) ($providerStatus['email']['configured'] ?? false);
            if (! $emailConfigured && app()->environment('production')) {
                $results[] = $this->result(
                    'notifications',
                    'warning',
                    'Password reset email provider is not configured (NOTIFICATION_EMAIL_CONFIGURED). Recovery still works via in-app when available, but customers will not receive email links.',
                );
            }

            if ($results === []) {
                $results[] = $this->result(
                    'notifications',
                    'healthy',
                    'Notification channels and event mappings look healthy.',
                );
            }

            return $results;
        } catch (Throwable) {
            return [
                $this->result('notifications', 'critical', 'Unable to resolve notification configuration.'),
            ];
        }
    }
}
