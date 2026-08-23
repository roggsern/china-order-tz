<?php

namespace App\Support\Settings;

use App\Enums\SettingType;

/**
 * Catalog of platform settings allowed through the Settings foundation engine.
 * Secrets must never appear here or in the settings table.
 */
final class SettingsDefinitions
{
    /**
     * @return array<string, array{group: string, short_key: string, type: SettingType, default: mixed, rules: list<string>}>
     */
    public static function all(): array
    {
        return [
            'features.maintenance_mode' => [
                'group' => 'features',
                'short_key' => 'maintenance_mode',
                'type' => SettingType::Boolean,
                'default' => false,
                'rules' => ['required', 'boolean'],
            ],
            'features.maintenance_message' => [
                'group' => 'features',
                'short_key' => 'maintenance_message',
                'type' => SettingType::String,
                'default' => '',
                'rules' => ['nullable', 'string', 'max:1000'],
            ],
            'features.flags' => [
                'group' => 'features',
                'short_key' => 'flags',
                'type' => SettingType::Json,
                'default' => [
                    'wishlist' => false,
                    'reviews' => false,
                    'new_checkout' => false,
                ],
                'rules' => ['required', 'array'],
            ],
            'payments.default_provider' => [
                'group' => 'payments',
                'short_key' => 'default_provider',
                'type' => SettingType::String,
                'default' => 'nmb',
                // mock retained for settings-foundation / local testing only.
                'rules' => ['required', 'string', 'in:nmb,snippe,mpesa,card,cash,bank_transfer,mock'],
            ],
            'payments.enabled_methods' => [
                'group' => 'payments',
                'short_key' => 'enabled_methods',
                'type' => SettingType::Json,
                'default' => [
                    'nmb' => true,
                    'snippe' => false,
                    'mpesa' => false,
                    'card' => false,
                    'cash' => false,
                    'bank_transfer' => false,
                ],
                'rules' => ['required', 'array'],
            ],
            'notifications.email_enabled' => [
                'group' => 'notifications',
                'short_key' => 'email_enabled',
                'type' => SettingType::Boolean,
                'default' => false,
                'rules' => ['required', 'boolean'],
            ],
            'notifications.sms_enabled' => [
                'group' => 'notifications',
                'short_key' => 'sms_enabled',
                'type' => SettingType::Boolean,
                'default' => false,
                'rules' => ['required', 'boolean'],
            ],
            'notifications.whatsapp_enabled' => [
                'group' => 'notifications',
                'short_key' => 'whatsapp_enabled',
                'type' => SettingType::Boolean,
                'default' => false,
                'rules' => ['required', 'boolean'],
            ],
            'notifications.push_enabled' => [
                'group' => 'notifications',
                'short_key' => 'push_enabled',
                'type' => SettingType::Boolean,
                // Wave 6C: allow push channel when provider is configured (kill-switch remains admin-togglable).
                'default' => true,
                'rules' => ['required', 'boolean'],
            ],
            'notifications.in_app_enabled' => [
                'group' => 'notifications',
                'short_key' => 'in_app_enabled',
                'type' => SettingType::Boolean,
                'default' => true,
                'rules' => ['required', 'boolean'],
            ],
            'notifications.event_channel_map' => [
                'group' => 'notifications',
                'short_key' => 'event_channel_map',
                'type' => SettingType::Json,
                'default' => [
                    'order.created' => ['in_app', 'whatsapp', 'email', 'push'],
                    'order.paid' => ['in_app', 'whatsapp', 'email', 'push'],
                    'shipment.delivered' => ['in_app', 'whatsapp', 'email', 'push'],
                ],
                'rules' => ['required', 'array'],
            ],
            'shipping.duration_source' => [
                'group' => 'shipping',
                'short_key' => 'duration_source',
                'type' => SettingType::String,
                'default' => 'database',
                'rules' => ['required', 'string', 'in:database,fallback'],
            ],
        ];
    }

    /**
     * @return array<string, array{group: string, short_key: string, type: SettingType, default: mixed, rules: list<string>}>
     */
    public static function forGroup(string $group): array
    {
        return array_filter(
            self::all(),
            static fn (array $def): bool => $def['group'] === $group,
        );
    }

    /**
     * @return array{group: string, short_key: string, type: SettingType, default: mixed, rules: list<string>}|null
     */
    public static function get(string $fullKey): ?array
    {
        return self::all()[$fullKey] ?? null;
    }

    public static function fullKey(string $group, string $shortKey): string
    {
        return $group.'.'.$shortKey;
    }

    /**
     * @return list<string>
     */
    public static function groups(): array
    {
        return array_values(array_unique(array_map(
            static fn (array $def): string => $def['group'],
            self::all(),
        )));
    }
}
