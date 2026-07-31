<?php

namespace App\Services\Settings;

use App\Support\Settings\SettingsDefinitions;
use Illuminate\Support\Facades\Cache;

/**
 * Cache abstraction for the settings foundation engine.
 */
final class SettingsCache
{
    public const TTL_SECONDS = 300;

    public function keyForSetting(string $fullKey): string
    {
        return 'settings:key:'.$fullKey;
    }

    public function keyForGroup(string $group): string
    {
        return 'settings:group:'.$group;
    }

    public function keyForAll(): string
    {
        return 'settings:all';
    }

    public function remember(string $cacheKey, callable $resolver): mixed
    {
        return Cache::remember($cacheKey, self::TTL_SECONDS, $resolver);
    }

    public function forgetSetting(string $fullKey, ?string $group = null): void
    {
        Cache::forget($this->keyForSetting($fullKey));
        Cache::forget($this->keyForAll());

        if ($group !== null) {
            Cache::forget($this->keyForGroup($group));
        }
    }

    public function forgetGroup(string $group): void
    {
        Cache::forget($this->keyForGroup($group));
        Cache::forget($this->keyForAll());

        foreach (array_keys(SettingsDefinitions::forGroup($group)) as $fullKey) {
            Cache::forget($this->keyForSetting($fullKey));
        }
    }

    public function flushAll(): void
    {
        Cache::forget($this->keyForAll());
    }
}
