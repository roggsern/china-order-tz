<?php

namespace App\Services\Stores;

use App\Events\Audit\StorePlatformAudit;
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

            event(StorePlatformAudit::storeCreated($store, $actor));

            return $store->fresh(['inventoryLocations', 'terminals']);
        });
    }

    public function update(Store $store, array $data, ?Admin $actor = null): Store
    {
        $wasActive = $store->is_active;

        $store->fill([
            'name' => $data['name'] ?? $store->name,
            'slug' => isset($data['slug']) ? Str::slug((string) $data['slug']) : $store->slug,
            'description' => array_key_exists('description', $data) ? $data['description'] : $store->description,
            'logo_path' => array_key_exists('logo_path', $data) ? $data['logo_path'] : $store->logo_path,
            'banner_path' => array_key_exists('banner_path', $data) ? $data['banner_path'] : $store->banner_path,
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
            'settings' => array_key_exists('settings', $data) ? $data['settings'] : $store->settings,
        ])->save();

        if ($wasActive !== $store->is_active) {
            event(StorePlatformAudit::storeStatus($store, $store->is_active, $actor));
        }

        return $store->fresh(['inventoryLocations', 'terminals']);
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
}
