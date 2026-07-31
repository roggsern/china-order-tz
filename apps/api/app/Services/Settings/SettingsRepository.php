<?php

namespace App\Services\Settings;

use App\Enums\SettingType;
use App\Models\Admin;
use App\Models\Setting;
use Illuminate\Support\Collection;

final class SettingsRepository
{
    /**
     * @return Collection<int, Setting>
     */
    public function allActive(): Collection
    {
        return Setting::query()
            ->active()
            ->with(['updatedByAdmin:id,name,email'])
            ->orderBy('group')
            ->orderBy('key')
            ->get();
    }

    /**
     * @return Collection<int, Setting>
     */
    public function activeForGroup(string $group): Collection
    {
        return Setting::query()
            ->active()
            ->forGroup($group)
            ->with(['updatedByAdmin:id,name,email'])
            ->orderBy('key')
            ->get();
    }

    public function findActiveByKey(string $fullKey): ?Setting
    {
        return Setting::query()
            ->active()
            ->where('key', $fullKey)
            ->with(['updatedByAdmin:id,name,email'])
            ->first();
    }

    public function findByKey(string $fullKey): ?Setting
    {
        return Setting::query()
            ->where('key', $fullKey)
            ->with(['updatedByAdmin:id,name,email'])
            ->first();
    }

    /**
     * @param  array{
     *   key: string,
     *   group: string,
     *   type: SettingType,
     *   value: string,
     *   is_active?: bool
     * }  $attributes
     */
    public function upsert(array $attributes, ?Admin $actor = null): Setting
    {
        $existing = $this->findByKey($attributes['key']);

        if ($existing === null) {
            return Setting::query()->create([
                'key' => $attributes['key'],
                'group' => $attributes['group'],
                'type' => $attributes['type'],
                'value' => $attributes['value'],
                'is_active' => $attributes['is_active'] ?? true,
                'created_by' => $actor?->id,
                'updated_by' => $actor?->id,
            ])->load(['updatedByAdmin:id,name,email']);
        }

        $existing->fill([
            'group' => $attributes['group'],
            'type' => $attributes['type'],
            'value' => $attributes['value'],
            'is_active' => $attributes['is_active'] ?? $existing->is_active,
            'updated_by' => $actor?->id,
        ])->save();

        return $existing->fresh(['updatedByAdmin:id,name,email']) ?? $existing;
    }
}
