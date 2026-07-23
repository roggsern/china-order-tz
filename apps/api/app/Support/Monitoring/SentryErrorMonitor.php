<?php

namespace App\Support\Monitoring;

use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Sentry-compatible foundation. Activates when Sentry SDK helpers exist
 * and ERROR_MONITORING_DRIVER=sentry. Otherwise falls back to log monitor.
 */
final class SentryErrorMonitor implements ErrorMonitor
{
    private bool $warned = false;

    public function capture(Throwable $throwable, array $context = []): void
    {
        try {
            $safe = SafeContextRedactor::redact($context);
            $safeThrowable = SafeContextRedactor::redactThrowable($throwable);

            if (function_exists('\\Sentry\\captureException')) {
                if (function_exists('\\Sentry\\configureScope')) {
                    \Sentry\configureScope(function ($scope) use ($safe, $safeThrowable): void {
                        if (method_exists($scope, 'setTag')) {
                            $scope->setTag('environment', (string) config('monitoring.environment'));
                            $scope->setTag('release', (string) config('monitoring.release'));
                            $scope->setTag('exception_class', $safeThrowable['class']);
                        }
                        if (method_exists($scope, 'setExtra')) {
                            foreach ($safe as $key => $value) {
                                if (is_scalar($value) || $value === null) {
                                    $scope->setExtra((string) $key, $value);
                                }
                            }
                            $scope->setExtra('original_exception_class', $safeThrowable['class']);
                        }
                    });
                }

                // Never ship raw exception messages that may embed secrets.
                \Sentry\captureException(new RuntimeException(
                    $safeThrowable['class'].': '.$safeThrowable['message']
                ));

                return;
            }

            if (! $this->warned) {
                $this->warned = true;
                Log::warning('monitoring.sentry_unavailable', [
                    'reason' => 'sentry_sdk_not_installed',
                    'driver' => 'sentry',
                ]);
            }

            app(LogErrorMonitor::class)->capture($throwable, $safe);
        } catch (Throwable) {
            // Never break application execution for monitoring.
        }
    }
}
