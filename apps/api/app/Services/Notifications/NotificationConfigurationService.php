<?php

namespace App\Services\Notifications;

use App\Events\Audit\NotificationConfigurationUpdatedAudit;
use App\Models\Admin;
use App\Services\Settings\SettingsService;
use App\Support\Settings\SettingsSecretGuard;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Admin write path for notification delivery settings (Settings group notifications).
 * Never accepts or persists provider secrets.
 */
final class NotificationConfigurationService
{
    public function __construct(
        private readonly SettingsService $settings,
        private readonly NotificationConfigurationResolver $resolver,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function getConfig(): array
    {
        return $this->resolver->presentConfig();
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function updateConfig(array $payload, ?Admin $actor = null): array
    {
        $this->rejectSecretPayload($payload);

        $values = $this->normalizeUpdatePayload($payload);
        if ($values === []) {
            throw ValidationException::withMessages([
                'config' => ['At least one channel toggle or event mapping is required.'],
            ]);
        }

        return DB::transaction(function () use ($values, $actor) {
            $before = $this->resolver->snapshot();
            $this->settings->updateGroup(NotificationConfigurationResolver::GROUP, $values, $actor);
            $after = $this->resolver->snapshot();

            event(NotificationConfigurationUpdatedAudit::fromChange($before, $after, $actor));

            return $this->resolver->presentConfig();
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function normalizeUpdatePayload(array $payload): array
    {
        $values = [];

        $channels = is_array($payload['channels'] ?? null) ? $payload['channels'] : $payload;
        foreach (NotificationConfigurationResolver::CHANNEL_KEYS as $key) {
            if (array_key_exists($key, $channels)) {
                $values[$key] = (bool) $channels[$key];
            }
        }

        if (array_key_exists('event_channel_map', $payload)) {
            $values[NotificationConfigurationResolver::EVENT_MAP_KEY] = $this->validateEventMap(
                $payload['event_channel_map'],
            );
        }

        return $values;
    }

    /**
     * @return array<string, list<string>>
     */
    private function validateEventMap(mixed $map): array
    {
        if (! is_array($map)) {
            throw ValidationException::withMessages([
                'event_channel_map' => ['Event channel map must be an object of event keys to channel arrays.'],
            ]);
        }

        $allowedEvents = array_keys(NotificationConfigurationResolver::MANAGED_EVENTS);
        $normalized = $this->resolver->defaultEventChannelMap();

        foreach ($map as $eventKey => $channels) {
            if (! is_string($eventKey) || ! in_array($eventKey, $allowedEvents, true)) {
                throw ValidationException::withMessages([
                    'event_channel_map' => ["Unknown or unsupported event key [{$eventKey}]."],
                ]);
            }

            if (! is_array($channels)) {
                throw ValidationException::withMessages([
                    "event_channel_map.{$eventKey}" => ['Channels must be an array of channel names.'],
                ]);
            }

            $clean = [];
            foreach ($channels as $channel) {
                if (! is_string($channel)) {
                    throw ValidationException::withMessages([
                        "event_channel_map.{$eventKey}" => ['Each channel must be a string.'],
                    ]);
                }
                $value = strtolower(trim($channel));
                if (! in_array($value, NotificationConfigurationResolver::ALLOWED_CHANNELS, true)) {
                    throw ValidationException::withMessages([
                        "event_channel_map.{$eventKey}" => ["Unsupported channel [{$channel}]."],
                    ]);
                }
                $clean[] = $value;
            }

            $clean = array_values(array_unique($clean));
            if ($clean === []) {
                throw ValidationException::withMessages([
                    "event_channel_map.{$eventKey}" => ['At least one channel is required.'],
                ]);
            }

            $normalized[$eventKey] = $clean;
        }

        return $normalized;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function rejectSecretPayload(array $payload): void
    {
        $stack = [$payload];
        while ($stack !== []) {
            $current = array_pop($stack);
            if (! is_array($current)) {
                continue;
            }
            foreach ($current as $key => $value) {
                if (is_string($key) && SettingsSecretGuard::isSecretKey($key)) {
                    throw ValidationException::withMessages([
                        $key => ['Provider secrets cannot be stored in notification configuration. Keep SMTP/API credentials in ENV only.'],
                    ]);
                }
                if (is_array($value)) {
                    $stack[] = $value;
                }
            }
        }
    }
}
