<?php

namespace App\Support\Monitoring;

/**
 * Strip secrets / PII-sensitive material from monitoring payloads and messages.
 */
final class SafeContextRedactor
{
    /** @var list<string> */
    private const BLOCKED_KEY_FRAGMENTS = [
        'password',
        'passwd',
        'secret',
        'token',
        'authorization',
        'api_key',
        'apikey',
        'access_key',
        'private_key',
        'client_secret',
        'nmb_password',
        'webhook_secret',
        'cookie',
        'credit_card',
        'card_number',
        'cvv',
        'cvc',
        'pin',
        'bearer',
        'nmb_secret',
        'nmb_api',
    ];

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public static function redact(array $context): array
    {
        $out = [];
        foreach ($context as $key => $value) {
            $keyStr = (string) $key;
            if (self::isBlockedKey($keyStr)) {
                $out[$keyStr] = '[REDACTED]';
                continue;
            }

            if (is_array($value)) {
                /** @var array<string, mixed> $value */
                $out[$keyStr] = self::redact($value);
                continue;
            }

            if (is_string($value)) {
                $out[$keyStr] = self::redactString($value);
                continue;
            }

            $out[$keyStr] = $value;
        }

        return $out;
    }

    public static function redactString(string $value): string
    {
        if ($value === '') {
            return $value;
        }

        if (self::looksLikeSecret($value)) {
            return '[REDACTED]';
        }

        $patterns = [
            '/(?i)\b(password|passwd|secret|token|api[_-]?key|authorization|bearer|private[_-]?key|nmb[_-]?password|nmb[_-]?secret|webhook[_-]?secret|cvv|cvc)\b\s*[:=]\s*[^\s,;]+/',
            '/(?i)Bearer\s+[A-Za-z0-9\-._~+\/]+=*/',
            '/(?i)sk_live_[A-Za-z0-9]+/',
            '/(?i)-----BEGIN[A-Z ]*PRIVATE KEY-----[\s\S]*?-----END[A-Z ]*PRIVATE KEY-----/',
        ];

        $redacted = $value;
        foreach ($patterns as $pattern) {
            $redacted = (string) preg_replace($pattern, '[REDACTED]', $redacted);
        }

        return $redacted;
    }

    /**
     * @return array{class: string, message: string}
     */
    public static function redactThrowable(\Throwable $throwable): array
    {
        return [
            'class' => $throwable::class,
            'message' => self::redactString($throwable->getMessage()),
        ];
    }

    private static function isBlockedKey(string $key): bool
    {
        $normalized = strtolower($key);
        foreach (self::BLOCKED_KEY_FRAGMENTS as $fragment) {
            if (str_contains($normalized, $fragment)) {
                return true;
            }
        }

        return false;
    }

    private static function looksLikeSecret(string $value): bool
    {
        if (preg_match('/^Bearer\s+\S+/i', $value) === 1) {
            return true;
        }

        if (preg_match('/^sk_live_/i', $value) === 1) {
            return true;
        }

        if (str_contains($value, 'BEGIN') && str_contains($value, 'PRIVATE KEY')) {
            return true;
        }

        return false;
    }
}
