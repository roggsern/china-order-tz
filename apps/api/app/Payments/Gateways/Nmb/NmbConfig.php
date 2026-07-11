<?php

namespace App\Payments\Gateways\Nmb;

class NmbConfig
{
    /**
     * @var array<string, string>
     */
    private const SERVICES_NESTED_KEYS = [
        'http_timeout' => 'services.nmb.http.timeout',
        'http_connect_timeout' => 'services.nmb.http.connect_timeout',
        'http_retry_times' => 'services.nmb.http.retry_times',
        'log_channel' => 'services.nmb.logging.channel',
        'webhook_require_signature' => 'services.nmb.webhook.require_signature',
        'webhook_replay_ttl_seconds' => 'services.nmb.webhook.replay_ttl_seconds',
    ];

    public static function get(string $key, mixed $default = null): mixed
    {
        $servicesKey = self::SERVICES_NESTED_KEYS[$key] ?? "services.nmb.{$key}";
        $servicesValue = config($servicesKey);

        if (! self::isUnset($servicesValue)) {
            return $servicesValue;
        }

        return config("payments.nmb.{$key}", $default);
    }

    public static function username(): string
    {
        $merchantId = self::merchantId();

        if (self::servicesProfileActive()) {
            $servicesMerchantId = (string) config('services.nmb.merchant_id');
            $servicesUsername = config('services.nmb.username');

            if (filled($servicesUsername) && filled($servicesMerchantId)) {
                $expectedDefault = "merchant.{$servicesMerchantId}";

                if ($servicesUsername === $expectedDefault) {
                    return $servicesUsername;
                }

                if (! self::isMpgsDefaultUsername((string) $servicesUsername)) {
                    return (string) $servicesUsername;
                }
            }

            if (! filled($servicesUsername) && filled($paymentsUsername = config('payments.nmb.username'))) {
                return (string) $paymentsUsername;
            }

            return "merchant.{$merchantId}";
        }

        if (filled($username = config('payments.nmb.username'))) {
            return (string) $username;
        }

        return "merchant.{$merchantId}";
    }

    private static function isMpgsDefaultUsername(string $username): bool
    {
        return str_starts_with($username, 'merchant.');
    }

    public static function password(): string
    {
        return (string) self::get('password', '');
    }

    public static function merchantId(): string
    {
        return (string) self::get('merchant_id', '');
    }

    public static function servicesProfileActive(): bool
    {
        return filled(config('services.nmb.merchant_id'))
            || filled(config('services.nmb.base_url'));
    }

    private static function isUnset(mixed $value): bool
    {
        return $value === null || $value === '';
    }
}
