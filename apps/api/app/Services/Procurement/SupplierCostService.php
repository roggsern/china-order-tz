<?php

namespace App\Services\Procurement;

use App\Models\PurchaseOrderItem;
use App\Models\SupplierCostHistory;
use App\Models\SupplierProduct;
use Illuminate\Support\Collection;

/**
 * Tracks purchase cost history and keeps supplier_products costs current.
 */
class SupplierCostService
{
    public function recordFromPurchaseItem(string $supplierId, PurchaseOrderItem $item): SupplierCostHistory
    {
        $history = SupplierCostHistory::query()->create([
            'supplier_id' => $supplierId,
            'product_variant_id' => $item->product_variant_id,
            'purchase_order_item_id' => $item->id,
            'purchase_cost' => $item->unit_cost,
            'currency' => $item->currency ?: 'TZS',
            'recorded_at' => now(),
        ]);

        SupplierProduct::query()->updateOrCreate(
            [
                'supplier_id' => $supplierId,
                'product_variant_id' => $item->product_variant_id,
            ],
            [
                'purchase_cost' => $item->unit_cost,
                'currency' => $item->currency ?: 'TZS',
                'is_active' => true,
            ],
        );

        return $history;
    }

    public function latestForVariant(string $variantId, ?string $supplierId = null): ?SupplierCostHistory
    {
        return SupplierCostHistory::query()
            ->where('product_variant_id', $variantId)
            ->when($supplierId, fn ($q) => $q->where('supplier_id', $supplierId))
            ->latest('recorded_at')
            ->first();
    }

    /**
     * @return Collection<int, SupplierCostHistory>
     */
    public function historyForVariant(string $variantId, ?string $supplierId = null, int $limit = 50): Collection
    {
        return SupplierCostHistory::query()
            ->where('product_variant_id', $variantId)
            ->when($supplierId, fn ($q) => $q->where('supplier_id', $supplierId))
            ->latest('recorded_at')
            ->limit($limit)
            ->get();
    }
}
