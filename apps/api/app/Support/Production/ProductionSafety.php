<?php

namespace App\Support\Production;

/**
 * Production safety gates for non-production-only tooling.
 */
final class ProductionSafety
{
    public static function isProduction(): bool
    {
        return app()->environment('production');
    }

    /**
     * Mock / simulate payment tools must never run in production.
     */
    public static function assertNonProductionTooling(string $tool): void
    {
        if (self::isProduction()) {
            abort(403, "{$tool} is disabled in production.");
        }
    }
}
