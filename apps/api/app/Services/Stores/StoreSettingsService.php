<?php

namespace App\Services\Stores;

use App\Events\Audit\StoreSettingsUpdatedAudit;
use App\Models\Admin;
use App\Models\Store;
use App\Support\Settings\SettingsSecretGuard;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Admin write path for store business settings stored in stores.settings JSON.
 * Deep-merges managed sections only — never wipes unrelated receipt/POS keys.
 */
final class StoreSettingsService
{
    public function __construct(
        private readonly StoreSettingsResolver $resolver,
        private readonly ActiveStoreContext $storeContext,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function getSettings(Store $store, Admin $actor): array
    {
        $this->storeContext->assertCanAccess($actor, $store);

        return $this->resolver->resolve($store);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function updateSettings(Store $store, array $payload, Admin $actor): array
    {
        $this->storeContext->assertCanAccess($actor, $store);
        $this->rejectSecretPayload($payload);

        $sectionPatch = $this->normalizeSectionPatch($payload);
        if ($sectionPatch === []) {
            throw ValidationException::withMessages([
                'settings' => ['At least one of business, receipt, customer, or social is required.'],
            ]);
        }

        return DB::transaction(function () use ($store, $sectionPatch, $actor) {
            /** @var Store $locked */
            $locked = Store::query()->whereKey($store->id)->lockForUpdate()->firstOrFail();
            $before = $this->resolver->snapshot($locked);

            $settings = is_array($locked->settings) ? $locked->settings : [];
            $merged = $this->mergeSections($settings, $sectionPatch);

            $locked->settings = $merged;
            $locked->save();

            $fresh = $locked->fresh() ?? $locked;
            $after = $this->resolver->snapshot($fresh);

            event(StoreSettingsUpdatedAudit::fromChange($fresh, $before, $after, $actor));

            return $this->resolver->resolve($fresh);
        });
    }

    /**
     * Deep-merge managed section keys while preserving all other settings JSON keys.
     *
     * @param  array<string, mixed>  $existing
     * @param  array<string, array<string, mixed>>  $sectionPatch
     * @return array<string, mixed>
     */
    public function mergeSections(array $existing, array $sectionPatch): array
    {
        $merged = $existing;

        foreach ($sectionPatch as $section => $values) {
            $current = is_array($merged[$section] ?? null) ? $merged[$section] : [];
            foreach ($values as $key => $value) {
                $current[$key] = $value;
            }
            $merged[$section] = $current;
        }

        return $merged;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, array<string, mixed>>
     */
    private function normalizeSectionPatch(array $payload): array
    {
        $sections = is_array($payload['settings'] ?? null) ? $payload['settings'] : $payload;
        $patch = [];

        foreach (StoreSettingsResolver::SECTIONS as $section) {
            if (! array_key_exists($section, $sections) || ! is_array($sections[$section])) {
                continue;
            }

            $normalized = [];
            foreach ($sections[$section] as $key => $value) {
                if (! is_string($key)) {
                    throw ValidationException::withMessages([
                        "{$section}" => ['Setting keys must be strings.'],
                    ]);
                }

                if (! in_array($key, StoreSettingsResolver::SECTION_KEYS[$section], true)) {
                    throw ValidationException::withMessages([
                        "{$section}.{$key}" => ["Unknown setting [{$key}] for section [{$section}]."],
                    ]);
                }

                if (SettingsSecretGuard::isSecretKey($key)) {
                    throw ValidationException::withMessages([
                        "{$section}.{$key}" => ['Secrets cannot be stored in store settings.'],
                    ]);
                }

                $normalized[$key] = $section === 'receipt' && $key === 'show_logo'
                    ? (bool) $value
                    : $this->stringOrEmpty($value, "{$section}.{$key}");
            }

            if ($normalized !== []) {
                $patch[$section] = $normalized;
            }
        }

        return $patch;
    }

    private function stringOrEmpty(mixed $value, string $field): string
    {
        if ($value === null) {
            return '';
        }

        if (! is_scalar($value)) {
            throw ValidationException::withMessages([
                $field => ['Value must be a string.'],
            ]);
        }

        return trim((string) $value);
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
                        $key => ['Passwords, API keys, and payment secrets cannot be stored in store settings.'],
                    ]);
                }
                if (is_array($value)) {
                    $stack[] = $value;
                }
            }
        }
    }
}
