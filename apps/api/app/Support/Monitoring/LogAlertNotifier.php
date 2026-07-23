<?php

namespace App\Support\Monitoring;

use Illuminate\Support\Facades\Log;
use Throwable;

final class LogAlertNotifier implements AlertNotifier
{
    public function notify(string $title, string $severity, array $context = []): void
    {
        try {
            Log::channel('stack')->warning('monitoring.alert', SafeContextRedactor::redact([
                'title' => $title,
                'severity' => $severity,
                'environment' => config('monitoring.environment'),
                'release' => config('monitoring.release'),
                'context' => $context,
            ]));
        } catch (Throwable) {
            // Never break application execution for alerting.
        }
    }
}
