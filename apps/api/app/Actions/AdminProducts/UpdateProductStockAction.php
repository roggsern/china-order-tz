<?php

namespace App\Actions\AdminProducts;

use App\Http\Requests\Admin\UpdateProductStockRequest;
use App\Models\Inventory;
use App\Models\Product;

class UpdateProductStockAction
{
    public function handle(UpdateProductStockRequest $request, Product $product): Product
    {
        Inventory::query()->updateOrCreate(
            [
                'product_id' => $product->id,
                'product_variant_id' => null,
            ],
            [
                'quantity' => $request->validated('stock_quantity'),
            ],
        );

        return $product->fresh()->load(['category', 'brand', 'inventory']);
    }
}
