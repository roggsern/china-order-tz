<?php

namespace App\Support\Settings;

/**
 * Prevents secret-like keys from entering the settings store or audit payloads.
 */
final class SettingsSecretGuard
{
    /** @var list<string> */
    private const FORBIDDEN_FRAGMENTS = [
        'password',
        'secret',
        'token',
        'api_key',
        'apikey',
        'private_key',
        'webhook_secret',
        'client_secret',
        'access_key',
        'nmb_password',
        'nmb_username',
        'app_key',
    ];

    public static function isSecretKey(string $key): bool
    {
        $normalized = strtolower(str_replace(['-', '.'], '_', $key));

        foreach (self::FORBIDDEN_FRAGMENTS as $fragment) {
            if (str_contains($normalized, $fragment)) {
                return true;
            }
        }

        return false;
    }

    public static function mask(mixed $value): string
    {
        return '[REDACTED]';
    }
}
