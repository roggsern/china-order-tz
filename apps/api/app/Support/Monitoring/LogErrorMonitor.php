<?php

namespace App\Support\Monitoring;

use Illuminate\Support\Facades\Log;
use Throwable;

final class LogErrorMonitor implements ErrorMonitor
{
    public function capture(Throwable $throwable, array $context = []): void
    {
        try {
            $safeThrowable = SafeContextRedactor::redactThrowable($throwable);

            Log::channel('stack')->error('monitoring.exception', SafeContextRedactor::redact(array_merge([
                'exception' => $safeThrowable['class'],
                'message' => $safeThrowable['message'],
                'file' => basename($throwable->getFile()),
                'line' => $throwable->getLine(),
                'environment' => config('monitoring.environment'),
                'release' => config('monitoring.release'),
                'request_id' => request()?->headers->get('X-Request-Id')
                    ?? (function_exists('request') ? data_get(request()?->attributes->all(), 'request_id') : null),
            ], $context)));
        } catch (Throwable) {
            // Never break application / exception reporting pipeline.
        }
    }
}
