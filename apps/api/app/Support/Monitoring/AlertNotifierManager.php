<?php

namespace App\Support\Monitoring;

use Throwable;

class AlertNotifierManager
{
    public function driver(?string $name = null): AlertNotifier
    {
        $name = strtolower($name ?? (string) config('monitoring.alerts.driver', 'log'));

        return match ($name) {
            'slack' => app(SlackWebhookAlertNotifier::class),
            default => app(LogAlertNotifier::class),
        };
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function alert(string $message, string $severity = 'warning', array $context = []): void
    {
        try {
            if (! config('monitoring.enabled', true)) {
                return;
            }

            $this->driver()->notify($message, $severity, $context);
        } catch (Throwable) {
            // Never break commerce / queue / request paths for alerting.
        }
    }
}
