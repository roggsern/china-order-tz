<?php

namespace App\Services\ConfigurationHealth\Concerns;

trait BuildsHealthCheckResult
{
    /**
     * @return array{group: string, status: string, message: string, severity: string}
     */
    protected function result(string $group, string $severity, string $message): array
    {
        $severity = match ($severity) {
            'critical', 'warning', 'info', 'healthy' => $severity,
            default => 'info',
        };

        $status = match ($severity) {
            'critical' => 'critical',
            'warning' => 'warning',
            'healthy' => 'healthy',
            default => 'info',
        };

        return [
            'group' => $group,
            'status' => $status,
            'message' => $message,
            'severity' => $severity === 'healthy' ? 'info' : $severity,
        ];
    }
}
