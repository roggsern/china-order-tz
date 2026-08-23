<?php

namespace App\Payments\Gateways\Snippe;

/**
 * Local/static validation of SNIPPE_WEBHOOK_URL. Does not perform network I/O.
 */
final class SnippeWebhookUrlValidator
{
    public const PATH = '/api/v1/payments/snippe/webhook';

    public static function isValid(string $url, bool $requireHttps = false): bool
    {
        $url = trim($url);
        if ($url === '') {
            return false;
        }

        if (strlen($url) > 500) {
            return false;
        }

        $parts = parse_url($url);
        if (! is_array($parts)) {
            return false;
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = trim((string) ($parts['host'] ?? ''));
        $path = rtrim((string) ($parts['path'] ?? ''), '/');

        if ($host === '' || $path !== self::PATH) {
            return false;
        }

        if ($requireHttps) {
            return $scheme === 'https';
        }

        return in_array($scheme, ['https', 'http'], true);
    }
}
