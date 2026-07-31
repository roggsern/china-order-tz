<?php

namespace App\Services\Settings;

use App\Enums\SettingType;
use App\Models\Admin;
use App\Models\Setting;
use App\Support\Settings\SettingsDefinitions;
use App\Support\Settings\SettingsSecretGuard;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class SettingsService
{
    public function __construct(
        private readonly SettingsRepository $repository,
        private readonly SettingsValueCaster $caster,
        private readonly SettingsCache $cache,
        private readonly SettingsAuditService $audit,
    ) {}

    public function get(string $key, mixed $default = null): mixed
    {
        $this->assertNotSecretKey($key);

        return $this->cache->remember(
            $this->cache->keyForSetting($key),
            function () use ($key, $default) {
                $setting = $this->repository->findActiveByKey($key);
                if ($setting === null) {
                    $definition = SettingsDefinitions::get($key);

                    return $definition['default'] ?? $default;
                }

                return $this->caster->fromStorage(
                    $setting->value,
                    $setting->type instanceof SettingType ? $setting->type : SettingType::String,
                );
            },
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getGroup(string $group): array
    {
        return $this->cache->remember(
            $this->cache->keyForGroup($group),
            function () use ($group) {
                return $this->repository->activeForGroup($group)
                    ->map(fn (Setting $setting) => $this->present($setting))
                    ->values()
                    ->all();
            },
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function all(): array
    {
        return $this->cache->remember(
            $this->cache->keyForAll(),
            function () {
                return $this->repository->allActive()
                    ->map(fn (Setting $setting) => $this->present($setting))
                    ->values()
                    ->all();
            },
        );
    }

    public function set(string $key, mixed $value, ?Admin $actor = null): array
    {
        $this->assertNotSecretKey($key);

        $definition = SettingsDefinitions::get($key);
        if ($definition === null) {
            throw ValidationException::withMessages([
                'key' => ["Unknown settings key [{$key}]."],
            ]);
        }

        $validated = $this->validateValue($key, $value, $definition['rules']);
        $type = $definition['type'];
        $stored = $this->caster->toStorage($validated, $type);

        return DB::transaction(function () use ($key, $definition, $type, $stored, $validated, $actor) {
            $existing = $this->repository->findByKey($key);
            $oldCast = $existing !== null
                ? $this->caster->fromStorage(
                    $existing->value,
                    $existing->type instanceof SettingType ? $existing->type : $type,
                )
                : null;

            $setting = $this->repository->upsert([
                'key' => $key,
                'group' => $definition['group'],
                'type' => $type,
                'value' => $stored,
                'is_active' => true,
            ], $actor);

            $this->cache->forgetSetting($key, $definition['group']);
            $this->audit->recordChange($setting, $oldCast, $validated, $actor);

            return $this->present($setting);
        });
    }

    /**
     * @param  array<string, mixed>  $data  short_key => value
     * @return list<array<string, mixed>>
     */
    public function updateGroup(string $group, array $data, ?Admin $actor = null): array
    {
        if ($data === []) {
            throw ValidationException::withMessages([
                'values' => ['At least one setting value is required.'],
            ]);
        }

        $definitions = SettingsDefinitions::forGroup($group);
        if ($definitions === []) {
            throw ValidationException::withMessages([
                'group' => ["Unknown settings group [{$group}]."],
            ]);
        }

        return DB::transaction(function () use ($group, $data, $definitions, $actor) {
            $updated = [];

            foreach ($data as $shortKey => $value) {
                $fullKey = SettingsDefinitions::fullKey($group, (string) $shortKey);
                $this->assertNotSecretKey($fullKey);

                if (! isset($definitions[$fullKey])) {
                    throw ValidationException::withMessages([
                        "values.{$shortKey}" => ["Unknown setting [{$shortKey}] for group [{$group}]."],
                    ]);
                }

                $updated[] = $this->set($fullKey, $value, $actor);
            }

            $this->cache->forgetGroup($group);

            return $updated;
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function present(Setting $setting): array
    {
        $type = $setting->type instanceof SettingType ? $setting->type : SettingType::String;
        $castValue = $this->caster->fromStorage($setting->value, $type);
        $safeValue = $this->audit->safeValue($setting->key, $castValue);

        return [
            'group' => $setting->group,
            'key' => $setting->key,
            'value' => $safeValue,
            'type' => $type->value,
            'is_active' => (bool) $setting->is_active,
            'updated_at' => $setting->updated_at?->toIso8601String(),
            'updated_by' => $setting->updatedByAdmin === null ? null : [
                'id' => $setting->updatedByAdmin->id,
                'name' => $setting->updatedByAdmin->name,
                'email' => $setting->updatedByAdmin->email,
            ],
        ];
    }

    /**
     * @param  list<string>  $rules
     */
    private function validateValue(string $key, mixed $value, array $rules): mixed
    {
        $validator = Validator::make(
            ['value' => $value],
            ['value' => $rules],
            [],
            ['value' => $key],
        );

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return $validator->validated()['value'];
    }

    private function assertNotSecretKey(string $key): void
    {
        if (SettingsSecretGuard::isSecretKey($key)) {
            throw ValidationException::withMessages([
                'key' => ['Secret values cannot be stored in settings.'],
            ]);
        }
    }
}
