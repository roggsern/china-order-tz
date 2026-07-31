<?php

namespace App\Services\Settings;

use App\Events\Audit\SettingsUpdatedAudit;
use App\Models\Admin;
use App\Models\Setting;
use App\Support\Settings\SettingsSecretGuard;

final class SettingsAuditService
{
    /**
     * @param  mixed  $oldCastValue
     * @param  mixed  $newCastValue
     */
    public function recordChange(
        Setting $setting,
        mixed $oldCastValue,
        mixed $newCastValue,
        ?Admin $actor = null,
    ): void {
        $oldPayload = [
            'key' => $setting->key,
            'value' => $this->safeValue($setting->key, $oldCastValue),
        ];
        $newPayload = [
            'key' => $setting->key,
            'value' => $this->safeValue($setting->key, $newCastValue),
        ];

        event(SettingsUpdatedAudit::fromChange(
            $setting,
            $oldPayload,
            $newPayload,
            $actor,
            [
                'actor_id' => $actor?->id,
            ],
        ));
    }

    public function safeValue(string $key, mixed $value): mixed
    {
        if (SettingsSecretGuard::isSecretKey($key)) {
            return SettingsSecretGuard::mask($value);
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function maskPayload(array $payload): array
    {
        $masked = [];
        foreach ($payload as $key => $value) {
            $masked[$key] = SettingsSecretGuard::isSecretKey((string) $key)
                ? SettingsSecretGuard::mask($value)
                : $value;
        }

        return $masked;
    }
}
