<?php

namespace App\Services\Stores;

use App\Events\Audit\StoreCreatedAudit;
use App\Events\Audit\StoreStatusChangedAudit;
use App\Events\Audit\StoreUpdatedAudit;
use App\Models\Admin;
use App\Models\InventoryLocation;
use App\Models\PosTerminal;
use App\Models\Store;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class StoreService
{
    public function create(array $data, ?Admin $actor = null): Store
    {
        return DB::transaction(function () use ($data, $actor) {
            $code = strtoupper(trim((string) ($data['code'] ?? '')));
            $name = trim((string) $data['name']);
            $slug = filled($data['slug'] ?? null)
                ? Str::slug((string) $data['slug'])
                : Str::slug($name);

            $store = Store::query()->create([
                'code' => $code,
                'name' => $name,
                'slug' => $slug,
                'description' => $data['description'] ?? null,
                // Seeders/internal callers may set paths; admin HTTP API prohibits path edits.
                'logo_path' => $data['logo_path'] ?? null,
                'banner_path' => $data['banner_path'] ?? null,
                'theme_color' => $data['theme_color'] ?? null,
                'is_active' => (bool) ($data['is_active'] ?? true),
                'storefront_enabled' => (bool) ($data['storefront_enabled'] ?? true),
                'storefront_visible' => (bool) ($data['storefront_visible'] ?? true),
                'storefront_featured' => (bool) ($data['storefront_featured'] ?? false),
                'storefront_sort_order' => array_key_exists('storefront_sort_order', $data)
                    ? $data['storefront_sort_order']
                    : ($data['sort_order'] ?? null),
                'sort_order' => (int) ($data['sort_order'] ?? 0),
                'settings' => $data['settings'] ?? null,
            ]);

            InventoryLocation::query()->create([
                'store_id' => $store->id,
                'code' => $code,
                'name' => $name.' Main',
                'is_default' => true,
                'is_active' => true,
            ]);

            PosTerminal::query()->create([
                'store_id' => $store->id,
                'code' => 'T1',
                'name' => 'Terminal 1',
                'is_active' => true,
            ]);

            $store = $store->fresh(['inventoryLocations', 'terminals']) ?? $store;

            event(StoreCreatedAudit::fromStore($store, $this->identitySnapshot($store), $actor));

            return $store;
        });
    }

    public function update(Store $store, array $data, ?Admin $actor = null): Store
    {
        $before = $this->identitySnapshot($store);
        $wasActive = (bool) $store->is_active;

        $store->fill([
            'name' => $data['name'] ?? $store->name,
            'slug' => isset($data['slug']) ? Str::slug((string) $data['slug']) : $store->slug,
            'description' => array_key_exists('description', $data) ? $data['description'] : $store->description,
            'theme_color' => array_key_exists('theme_color', $data) ? $data['theme_color'] : $store->theme_color,
            'is_active' => array_key_exists('is_active', $data) ? (bool) $data['is_active'] : $store->is_active,
            'storefront_enabled' => array_key_exists('storefront_enabled', $data)
                ? (bool) $data['storefront_enabled']
                : $store->storefront_enabled,
            'storefront_visible' => array_key_exists('storefront_visible', $data)
                ? (bool) $data['storefront_visible']
                : $store->storefront_visible,
            'storefront_featured' => array_key_exists('storefront_featured', $data)
                ? (bool) $data['storefront_featured']
                : $store->storefront_featured,
            'storefront_sort_order' => array_key_exists('storefront_sort_order', $data)
                ? $data['storefront_sort_order']
                : $store->storefront_sort_order,
            'sort_order' => array_key_exists('sort_order', $data) ? (int) $data['sort_order'] : $store->sort_order,
        ])->save();

        $store = $store->fresh(['inventoryLocations', 'terminals']) ?? $store;
        $after = $this->identitySnapshot($store);

        if ($before !== $after) {
            event(StoreUpdatedAudit::fromChange($store, $before, $after, $actor));
        }

        if ($wasActive !== (bool) $store->is_active) {
            event(StoreStatusChangedAudit::fromChange($store, $wasActive, (bool) $store->is_active, $actor));
        }

        return $store;
    }

    public function updateStatus(Store $store, bool $isActive, ?Admin $actor = null): Store
    {
        $wasActive = (bool) $store->is_active;
        if ($wasActive === $isActive) {
            return $store->fresh(['inventoryLocations', 'terminals']) ?? $store;
        }

        $store->is_active = $isActive;
        $store->save();
        $store = $store->fresh(['inventoryLocations', 'terminals']) ?? $store;

        event(StoreStatusChangedAudit::fromChange($store, $wasActive, $isActive, $actor));

        return $store;
    }

    public function defaultLocation(Store $store): InventoryLocation
    {
        $location = $store->defaultInventoryLocation
            ?? $store->inventoryLocations()->where('is_active', true)->orderByDesc('is_default')->first();

        if ($location === null) {
            throw ValidationException::withMessages([
                'store_id' => ['Store has no active inventory location.'],
            ]);
        }

        return $location;
    }

    /**
     * @return array<string, mixed>
     */
    public function identitySnapshot(Store $store): array
    {
        return [
            'id' => $store->id,
            'code' => $store->code,
            'name' => $store->name,
            'slug' => $store->slug,
            'description' => $store->description,
            'theme_color' => $store->theme_color,
            'is_active' => (bool) $store->is_active,
            'storefront_enabled' => (bool) $store->storefront_enabled,
            'storefront_visible' => (bool) $store->storefront_visible,
            'storefront_featured' => (bool) $store->storefront_featured,
            'storefront_sort_order' => $store->storefront_sort_order,
            'sort_order' => (int) $store->sort_order,
        ];
    }
}
