<?php

namespace App\Actions\AdminVariantInventories;

use App\Http\Requests\Admin\UpdateVariantInventoryRequest;
use App\Http\Resources\VariantInventoryResource;
use App\Models\VariantInventory;

class UpdateVariantInventoryAction
{
    /**
     * @return array<string, mixed>
     */
    public function handle(UpdateVariantInventoryRequest $request, VariantInventory $inventory): array
    {
        $data = $request->validated();

        $onHand = array_key_exists('on_hand', $data)
            ? (int) $data['on_hand']
            : (int) $inventory->on_hand;
        $reserved = array_key_exists('reserved', $data)
            ? (int) $data['reserved']
            : (int) $inventory->reserved;

        if (array_key_exists('reserve', $data)) {
            $reserved += (int) $data['reserve'];
        }

        if (array_key_exists('release', $data)) {
            $reserved = max(0, $reserved - (int) $data['release']);
        }

        $inventory->fill([
            'warehouse_code' => $data['warehouse_code'] ?? $inventory->warehouse_code,
            'on_hand' => $onHand,
            'reserved' => $reserved,
            'reorder_level' => array_key_exists('reorder_level', $data)
                ? (int) $data['reorder_level']
                : $inventory->reorder_level,
            'safety_stock' => array_key_exists('safety_stock', $data)
                ? (int) $data['safety_stock']
                : $inventory->safety_stock,
            'is_active' => array_key_exists('is_active', $data)
                ? (bool) $data['is_active']
                : $inventory->is_active,
        ]);
        $inventory->save();

        return (new VariantInventoryResource($inventory->fresh()))->resolve();
    }
}
