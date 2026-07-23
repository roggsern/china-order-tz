<?php

namespace App\Support\Security;

/**
 * RC1-G4B — allowlist for public media / thumbnail URLs.
 */
final class SafePublicUrl
{
    public static function isAllowed(?string $value): bool
    {
        if ($value === null || $value === '') {
            return true;
        }

        $value = trim(html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $value = preg_replace('/[\x00-\x1F\x7F]+/u', '', $value) ?? $value;

        if ($value === '') {
            return true;
        }

        $lower = strtolower(preg_replace('/\s+/', '', $value) ?? $value);
        if (
            str_starts_with($lower, 'javascript:')
            || str_starts_with($lower, 'data:')
            || str_starts_with($lower, 'vbscript:')
        ) {
            return false;
        }

        if (str_starts_with($value, '/storage/')) {
            return ! str_contains($value, '..')
                && ! str_contains($value, '\\')
                && ! preg_match('#^/storage//+#', $value);
        }

        if (! filter_var($value, FILTER_VALIDATE_URL)) {
            return false;
        }

        return (bool) preg_match('#^https?://#i', $value);
    }

    /**
     * Laravel validation closure factory.
     *
     * @return \Closure(string, mixed, \Closure): void
     */
    public static function rule(): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail): void {
            if ($value === null || $value === '') {
                return;
            }
            if (! is_string($value) || ! self::isAllowed($value)) {
                $fail('The '.$attribute.' must be an http(s) URL or safe /storage/ path.');
            }
        };
    }
}
