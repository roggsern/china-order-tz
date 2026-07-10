<?php

namespace App\Payments\Gateways\Nmb;

class NmbCallbackVerifier
{
    /**
     * @var array<int, string>
     */
    private const SENSITIVE_KEYS = [
        'password',
        'secret',
        'authorization',
        'apikey',
        'api_key',
        'token',
        'access_token',
        'refresh_token',
    ];

    /**
     * @param  array<string, mixed>  $payload
     */
    public function isValidPayload(array $payload): bool
    {
        if ($payload === []) {
            return false;
        }

        return filled($this->extractSessionId($payload))
            || filled($this->extractOrderReference($payload))
            || filled($payload['result'] ?? null);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function verify(array $payload): bool
    {
        return false;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function extractSessionId(array $payload): ?string
    {
        $session = is_array($payload['session'] ?? null) ? $payload['session'] : [];
        $candidates = [
            $session['id'] ?? null,
            $payload['sessionId'] ?? null,
            $payload['gateway_session_id'] ?? null,
        ];

        foreach ($candidates as $candidate) {
            if (filled($candidate)) {
                return (string) $candidate;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function extractOrderReference(array $payload): ?string
    {
        $order = is_array($payload['order'] ?? null) ? $payload['order'] : [];
        $candidates = [
            $order['id'] ?? null,
            $payload['orderId'] ?? null,
            $payload['reference'] ?? null,
        ];

        foreach ($candidates as $candidate) {
            if (filled($candidate)) {
                return (string) $candidate;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function sanitizeForLog(array $payload): array
    {
        $sanitized = [];

        foreach ($payload as $key => $value) {
            if (in_array(strtolower((string) $key), self::SENSITIVE_KEYS, true)) {
                $sanitized[$key] = '[REDACTED]';

                continue;
            }

            $sanitized[$key] = is_array($value)
                ? $this->sanitizeForLog($value)
                : $value;
        }

        return $sanitized;
    }
}
