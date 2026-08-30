<?php

namespace App\Services\Notifications;

use App\Enums\NotificationChannel;
use App\Enums\NotificationEventType;
use App\Services\Settings\SettingsService;
use Throwable;

/**
 * Resolves admin notification delivery configuration from Settings (notifications group).
 * Secrets stay in ENV; this only reads enablement toggles and event→channel maps.
 */
final class NotificationConfigurationResolver
{
    public const GROUP = 'notifications';

    /** @var list<string> */
    public const CHANNEL_KEYS = [
        'email_enabled',
        'sms_enabled',
        'whatsapp_enabled',
        'push_enabled',
        'in_app_enabled',
    ];

    public const EVENT_MAP_KEY = 'event_channel_map';

    /**
     * Admin-facing event keys → platform NotificationEventType values.
     *
     * @var array<string, NotificationEventType>
     */
    public const MANAGED_EVENTS = [
        'order.created' => NotificationEventType::OrderCreated,
        'order.paid' => NotificationEventType::PaymentConfirmed,
        'shipment.delivered' => NotificationEventType::OrderDelivered,
    ];

    /** @var list<string> */
    public const ALLOWED_CHANNELS = [
        'in_app',
        'email',
        'sms',
        'whatsapp',
        'push',
    ];

    public function __construct(
        private readonly SettingsService $settings,
        private readonly ChannelProviderRegistry $providers,
    ) {}

    public function isChannelEnabled(NotificationChannel|string $channel): bool
    {
        $value = $channel instanceof NotificationChannel ? $channel->value : $channel;

        $key = match ($value) {
            'email' => 'notifications.email_enabled',
            'sms' => 'notifications.sms_enabled',
            'whatsapp' => 'notifications.whatsapp_enabled',
            'push' => 'notifications.push_enabled',
            'in_app' => 'notifications.in_app_enabled',
            default => null,
        };

        if ($key === null) {
            return false;
        }

        try {
            return (bool) $this->settings->get($key, $value === 'in_app');
        } catch (Throwable) {
            return $value === 'in_app';
        }
    }

    /**
     * @return list<NotificationChannel>
     */
    public function enabledChannels(): array
    {
        $enabled = [];
        foreach (NotificationChannel::cases() as $channel) {
            if ($this->isChannelEnabled($channel)) {
                $enabled[] = $channel;
            }
        }

        return $enabled;
    }

    public function isProviderAvailable(NotificationChannel|string $channel): bool
    {
        try {
            $resolved = $channel instanceof NotificationChannel
                ? $channel
                : NotificationChannel::tryFrom((string) $channel);

            if ($resolved === null) {
                return false;
            }

            if ($resolved === NotificationChannel::InApp) {
                return true;
            }

            return $this->providers->resolve($resolved)->isConfigured();
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * @return array<string, list<string>>
     */
    public function eventChannelMap(): array
    {
        try {
            $map = $this->settings->get('notifications.'.self::EVENT_MAP_KEY);
        } catch (Throwable) {
            $map = null;
        }

        if (! is_array($map) || $map === []) {
            return $this->defaultEventChannelMap();
        }

        $normalized = [];
        foreach (array_keys(self::MANAGED_EVENTS) as $eventKey) {
            $channels = $map[$eventKey] ?? ['in_app'];
            if (! is_array($channels)) {
                $channels = ['in_app'];
            }
            $normalized[$eventKey] = $this->normalizeChannelList($channels);
            if ($normalized[$eventKey] === []) {
                $normalized[$eventKey] = ['in_app'];
            }
        }

        return $normalized;
    }

    /**
     * Resolve channels for a platform event type.
     * Returns null when there is no settings override (caller should use config defaults).
     *
     * @return list<NotificationChannel>|null
     */
    public function channelsForEventType(NotificationEventType $type): ?array
    {
        $adminKey = $this->adminKeyForEventType($type);
        if ($adminKey === null) {
            return null;
        }

        $map = $this->eventChannelMap();
        $channels = $map[$adminKey] ?? ['in_app'];

        return $this->toChannelEnums($channels);
    }

    /**
     * Filter channels by admin enablement and provider availability.
     * Falls back to in_app when external providers are unavailable or disabled.
     *
     * @param  list<NotificationChannel|string>  $channels
     * @return list<NotificationChannel>
     */
    public function filterForDelivery(array $channels, bool $requireProviderConfigured = true): array
    {
        $resolved = [];
        foreach ($channels as $channel) {
            $enum = $channel instanceof NotificationChannel
                ? $channel
                : NotificationChannel::tryFrom((string) $channel);
            if ($enum === null) {
                continue;
            }
            if (! $this->isChannelEnabled($enum)) {
                continue;
            }
            if ($requireProviderConfigured && ! $this->isProviderAvailable($enum)) {
                continue;
            }
            $resolved[] = $enum;
        }

        if ($resolved === [] && $this->isChannelEnabled(NotificationChannel::InApp)) {
            return [NotificationChannel::InApp];
        }

        return array_values(array_unique($resolved, SORT_REGULAR));
    }

    /**
     * Full deliverable channel list for managed settings events
     * (settings map → enablement → provider availability → in_app fallback).
     *
     * @return list<NotificationChannel>
     */
    public function resolveEventChannels(NotificationEventType|string $event): array
    {
        $type = $event instanceof NotificationEventType
            ? $event
            : (NotificationEventType::tryFrom((string) $event)
                ?? $this->eventTypeFromAdminKey((string) $event));

        if ($type === null) {
            return $this->filterForDelivery(['in_app']);
        }

        $fromSettings = $this->channelsForEventType($type);
        if ($fromSettings !== null) {
            return $this->filterForDelivery($fromSettings);
        }

        $configured = config('notifications.event_channels.'.$type->value, ['in_app']);
        if (! is_array($configured) || $configured === []) {
            $configured = ['in_app'];
        }

        // Apply the same enablement + provider-availability filter used for managed events
        // so stub/unconfigured channels (WhatsApp/email/sms) do not create failed rows.
        return $this->filterForDelivery($this->toChannelEnums($configured));
    }

    /**
     * @return array<string, mixed>
     */
    public function presentConfig(): array
    {
        return [
            'channels' => [
                'email_enabled' => $this->isChannelEnabled(NotificationChannel::Email),
                'sms_enabled' => $this->isChannelEnabled(NotificationChannel::Sms),
                'whatsapp_enabled' => $this->isChannelEnabled(NotificationChannel::WhatsApp),
                'push_enabled' => $this->isChannelEnabled(NotificationChannel::Push),
                'in_app_enabled' => $this->isChannelEnabled(NotificationChannel::InApp),
            ],
            'event_channel_map' => $this->eventChannelMap(),
            'provider_status' => [
                'in_app' => [
                    'configured' => true,
                    'driver' => 'in_app',
                ],
                'email' => [
                    'configured' => (bool) config('notifications.email.configured', false),
                    'driver' => (string) config('notifications.email.driver', 'smtp'),
                ],
                'sms' => [
                    'configured' => (bool) config('notifications.sms.configured', false),
                    'driver' => (string) config('notifications.sms.driver', 'twilio'),
                ],
                'whatsapp' => [
                    'configured' => (bool) config('notifications.whatsapp.configured', false),
                    'driver' => (string) config('notifications.whatsapp.driver', 'ghala'),
                ],
                'push' => [
                    'configured' => (bool) config('notifications.push.configured', false),
                    'driver' => (string) config('notifications.push.driver', 'expo'),
                ],
            ],
            'managed_events' => array_keys(self::MANAGED_EVENTS),
            'allowed_channels' => self::ALLOWED_CHANNELS,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function snapshot(): array
    {
        return [
            'channels' => [
                'email_enabled' => $this->isChannelEnabled(NotificationChannel::Email),
                'sms_enabled' => $this->isChannelEnabled(NotificationChannel::Sms),
                'whatsapp_enabled' => $this->isChannelEnabled(NotificationChannel::WhatsApp),
                'push_enabled' => $this->isChannelEnabled(NotificationChannel::Push),
                'in_app_enabled' => $this->isChannelEnabled(NotificationChannel::InApp),
            ],
            'event_channel_map' => $this->eventChannelMap(),
        ];
    }

    public function adminKeyForEventType(NotificationEventType $type): ?string
    {
        foreach (self::MANAGED_EVENTS as $adminKey => $mapped) {
            if ($mapped === $type) {
                return $adminKey;
            }
        }

        return null;
    }

    public function eventTypeFromAdminKey(string $adminKey): ?NotificationEventType
    {
        return self::MANAGED_EVENTS[$adminKey] ?? null;
    }

    /**
     * @return array<string, list<string>>
     */
    public function defaultEventChannelMap(): array
    {
        return [
            // Managed admin map — Wave 6C appends push (provider still gated by configured+enabled).
            'order.created' => ['in_app', 'whatsapp', 'email', 'push'],
            'order.paid' => ['in_app', 'whatsapp', 'email', 'push'],
            'shipment.delivered' => ['in_app', 'whatsapp', 'email', 'push'],
        ];
    }

    /**
     * @param  list<mixed>  $channels
     * @return list<string>
     */
    private function normalizeChannelList(array $channels): array
    {
        $normalized = [];
        foreach ($channels as $channel) {
            if (! is_string($channel)) {
                continue;
            }
            $value = strtolower(trim($channel));
            if (! in_array($value, self::ALLOWED_CHANNELS, true)) {
                continue;
            }
            $normalized[] = $value;
        }

        return array_values(array_unique($normalized));
    }

    /**
     * @param  list<string>  $channels
     * @return list<NotificationChannel>
     */
    private function toChannelEnums(array $channels): array
    {
        return collect($this->normalizeChannelList($channels))
            ->map(fn (string $value) => NotificationChannel::tryFrom($value))
            ->filter()
            ->values()
            ->all();
    }
}
