<?php

namespace App\Support\Monitoring;

use Throwable;

final class ErrorMonitorManager
{
    public function driver(?string $name = null): ErrorMonitor
    {
        $name = strtolower($name ?? (string) config('monitoring.error.driver', 'log'));

        return match ($name) {
            'sentry' => app(SentryErrorMonitor::class),
            default => app(LogErrorMonitor::class),
        };
    }

    /**
     * Convenience capture that never throws into the caller.
     *
     * @param  array<string, mixed>  $context
     */
    public function capture(Throwable $exception, array $context = []): void
    {
        try {
            if (! config('monitoring.enabled', true)) {
                return;
            }

            $this->driver()->capture($exception, $context);
        } catch (Throwable) {
            //
        }
    }
}
