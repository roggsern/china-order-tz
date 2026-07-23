<?php

namespace App\Services\ProductConfiguration;

use Illuminate\Validation\ValidationException;

/**
 * Configuration Template SKU pattern integrity (ADR 052 / Phase 1D-C).
 *
 * Official placeholder syntax (only):
 *   {ATTR:slug}
 *
 * Unsupported tokens (e.g. {SKU}, {FOO}) must not silently produce broken SKUs.
 */
final class SkuPatternRules
{
    private const ATTR_TOKEN = '/^ATTR:[a-z0-9\-]+$/i';

    /**
     * @throws ValidationException
     */
    public static function assertValid(?string $pattern, string $field = 'sku_pattern'): void
    {
        if ($pattern === null || trim($pattern) === '') {
            return;
        }

        if (! preg_match_all('/\{([^}]+)\}/', $pattern, $matches)) {
            return;
        }

        $unsupported = [];
        foreach ($matches[1] as $token) {
            if (! preg_match(self::ATTR_TOKEN, $token)) {
                $unsupported[] = '{'.$token.'}';
            }
        }

        if ($unsupported === []) {
            return;
        }

        throw ValidationException::withMessages([
            $field => [
                'Unsupported SKU pattern placeholder(s): '.implode(', ', array_unique($unsupported))
                    .'. Only {ATTR:slug} placeholders are supported.',
            ],
        ]);
    }
}
