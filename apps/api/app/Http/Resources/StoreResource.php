<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Store */
class StoreResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'logo_path' => $this->logo_path,
            'logo_url' => $this->logoUrl(),
            'banner_path' => $this->banner_path,
            'banner_url' => $this->bannerUrl(),
            'theme_color' => $this->theme_color,
            'is_active' => (bool) $this->is_active,
            'storefront_enabled' => (bool) ($this->storefront_enabled ?? true),
            'storefront_visible' => (bool) ($this->storefront_visible ?? true),
            'storefront_featured' => (bool) ($this->storefront_featured ?? false),
            'storefront_sort_order' => $this->storefront_sort_order,
            'sort_order' => (int) $this->sort_order,
            'settings' => $this->settings,
            'categories' => $this->whenLoaded('categories', function () {
                return CustomerCategoryResource::collection($this->categories);
            }),
            'default_inventory_location' => $this->whenLoaded('defaultInventoryLocation', fn () => [
                'id' => $this->defaultInventoryLocation?->id,
                'code' => $this->defaultInventoryLocation?->code,
                'name' => $this->defaultInventoryLocation?->name,
            ]),
            'inventory_locations' => $this->whenLoaded('inventoryLocations', fn () => $this->inventoryLocations->map(fn ($loc) => [
                'id' => $loc->id,
                'code' => $loc->code,
                'name' => $loc->name,
                'is_default' => $loc->is_default,
                'is_active' => $loc->is_active,
            ])->values()),
            'terminals' => $this->whenLoaded('terminals', fn () => $this->terminals->map(fn ($t) => [
                'id' => $t->id,
                'code' => $t->code,
                'name' => $t->name,
                'is_active' => $t->is_active,
            ])->values()),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
