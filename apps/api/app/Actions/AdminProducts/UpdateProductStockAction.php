<?php

namespace App\Actions\AdminProducts;

use App\Http\Requests\Admin\UpdateProductStockRequest;
use App\Models\Inventory;
use App\Models\Product;

class UpdateProductStockAction
{
    public function handle(UpdateProductStockRequest $request, Product $product): Product
    {
        $newQuantity = $request->validated('stock_quantity');

        $existingInventory = Inventory::query()
            ->where('product_id', $product->id)
            ->whereNull('product_variant_id')
            ->first();

        $oldQuantity = $existingInventory?->quantity ?? 0;

        $inventory = Inventory::query()->updateOrCreate(
            [
                'product_id' => $product->id,
                'product_variant_id' => null,
            ],
            [
                'quantity' => $newQuantity,
            ],
        );

        $difference = $newQuantity - $oldQuantity;

        if ($difference !== 0) {
            $inventory->movements()->create([
                'quantity' => $difference,
                'type' => 'adjustment',
                'reason' => 'Admin stock update',
            ]);
        }

        return $product->fresh()->load(['category', 'brand', 'inventory']);
    }
}
