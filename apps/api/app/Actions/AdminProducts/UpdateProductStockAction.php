<?php

namespace App\Actions\AdminProducts;

use App\Http\Requests\Admin\UpdateProductStockRequest;
use App\Models\Admin;
use App\Models\Product;
use App\Services\Inventory\AdminInventoryApplicationService;
use Illuminate\Support\Facades\Auth;

class UpdateProductStockAction
{
    public function __construct(
        private readonly AdminInventoryApplicationService $adminInventory,
    ) {}

    public function handle(UpdateProductStockRequest $request, Product $product): Product
    {
        /** @var Admin|null $admin */
        $admin = Auth::user() instanceof Admin ? Auth::user() : null;
        $validated = $request->validated();
        $target = (int) $validated['stock_quantity'];
        $idempotencyKey = isset($validated['idempotency_key']) && is_string($validated['idempotency_key'])
            ? $validated['idempotency_key']
            : null;

        $this->adminInventory->setSimpleProductStock(
            product: $product,
            targetQuantity: $target,
            actor: $admin,
            reason: $idempotencyKey ?? 'Admin stock update',
            idempotencyKey: $idempotencyKey,
        );

        return $product->fresh()->load(['category', 'brand', 'inventory']);
    }
}
