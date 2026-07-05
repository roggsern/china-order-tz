<?php

namespace App\Actions\AdminProducts;

use App\Models\InventoryMovement;
use App\Models\Product;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class GetInventoryMovementsAction
{
    public function handle(Product $product): LengthAwarePaginator
    {
        return InventoryMovement::query()
            ->whereHas('inventory', fn ($query) => $query->where('product_id', $product->id))
            ->latest()
            ->paginate(20);
    }
}
