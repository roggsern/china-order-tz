<?php

namespace App\Actions\AdminVariantInventories;

use App\Http\Requests\Admin\StoreVariantInventoryRequest;
use App\Http\Resources\VariantInventoryResource;
use App\Models\ProductVariant;
use App\Models\VariantInventory;

class CreateVariantInventoryAction
{
    /**
     * @return array<string, mixed>
     */
    public function handle(StoreVariantInventoryRequest $request, ProductVariant $variant): array
    {
        $data = $request->validated();

        $inventory = VariantInventory::query()->create([
            'product_variant_id' => $variant->id,
            'warehouse_code' => $data['warehouse_code'] ?? 'MAIN',
            'on_hand' => (int) ($data['on_hand'] ?? 0),
            'reserved' => (int) ($data['reserved'] ?? 0),
            'reorder_level' => (int) ($data['reorder_level'] ?? 5),
            'safety_stock' => (int) ($data['safety_stock'] ?? 0),
            'is_active' => (bool) ($data['is_active'] ?? true),
        ]);

        return (new VariantInventoryResource($inventory->fresh()))->resolve();
    }
}
