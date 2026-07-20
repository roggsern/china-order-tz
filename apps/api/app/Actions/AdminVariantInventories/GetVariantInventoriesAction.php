<?php

namespace App\Actions\AdminVariantInventories;

use App\Http\Resources\VariantInventoryResource;
use App\Models\ProductVariant;

class GetVariantInventoriesAction
{
    /**
     * @return list<array<string, mixed>>
     */
    public function handle(ProductVariant $variant): array
    {
        $rows = $variant->inventories()
            ->orderBy('warehouse_code')
            ->get();

        return VariantInventoryResource::collection($rows)->resolve();
    }
}
