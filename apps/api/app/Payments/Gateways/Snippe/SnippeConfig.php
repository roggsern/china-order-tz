<?php

namespace App\Payments\Gateways\Snippe;

class SnippeConfig
{
    public static function get(string $key, mixed $default = null): mixed
    {
        return config("payments.snippe.{$key}", $default);
    }

    public static function enabled(): bool
    {
        return (bool) self::get('enabled', false);
    }

    public static function baseUrl(): string
    {
        return rtrim((string) self::get('base_url', 'https://api.snippe.sh'), '/');
    }

    public static function apiKey(): string
    {
        return (string) self::get('api_key', '');
    }

    public static function webhookSecret(): string
    {
        return (string) self::get('webhook_secret', '');
    }

    public static function webhookUrl(): string
    {
        return (string) self::get('webhook_url', '');
    }

    /**
     * Credentials required to call Snippe (GET/POST). Used for in-flight verify.
     */
    public static function hasOperationalCredentials(): bool
    {
        return trim(self::apiKey()) !== ''
            && trim(self::baseUrl()) !== '';
    }

    /**
     * Webhook delivery + signature configuration required before new collections.
     */
    public static function hasCollectionWebhookConfig(): bool
    {
        return trim(self::webhookSecret()) !== ''
            && self::webhookUrlIsValid();
    }

    public static function webhookUrlIsValid(): bool
    {
        return SnippeWebhookUrlValidator::isValid(
            self::webhookUrl(),
            requireHttps: app()->environment('production'),
        );
    }

    /**
     * Full readiness for customer selection and new initiation.
     */
    public static function isConfigured(): bool
    {
        return self::enabled()
            && self::hasOperationalCredentials()
            && self::hasCollectionWebhookConfig();
    }
}
