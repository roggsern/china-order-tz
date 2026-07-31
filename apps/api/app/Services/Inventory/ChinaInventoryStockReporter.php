<?php

namespace App\Services\Inventory;

use App\Enums\InventoryWarehouseCode;
use App\Models\VariantInventory;

/**
 * Reporting buckets for China vs Tanzania sellable vs in-transit stock.
 * Does not expose settings internals; reads VariantInventory warehouse_code only.
 */
final class ChinaInventoryStockReporter
{
    /**
     * @return array{
     *     china_stock: array{warehouse_code: string, on_hand: int, reserved: int, available: int, sku_count: int},
     *     tz_sellable_stock: array{warehouse_code: string, on_hand: int, reserved: int, available: int, sku_count: int},
     *     in_transit_stock: array{warehouse_code: string, on_hand: int, reserved: int, available: int, sku_count: int}
     * }
     */
    public function summarize(?string $productVariantId = null): array
    {
        return [
            'china_stock' => $this->bucket(InventoryWarehouseCode::China, $productVariantId),
            'tz_sellable_stock' => $this->bucket(InventoryWarehouseCode::Main, $productVariantId),
            'in_transit_stock' => $this->bucket(InventoryWarehouseCode::InTransit, $productVariantId),
        ];
    }

    /**
     * @return array{warehouse_code: string, on_hand: int, reserved: int, available: int, sku_count: int}
     */
    private function bucket(InventoryWarehouseCode $warehouse, ?string $productVariantId): array
    {
        $query = VariantInventory::query()
            ->where('warehouse_code', $warehouse->value)
            ->where('is_active', true);

        if ($productVariantId !== null) {
            $query->where('product_variant_id', $productVariantId);
        }

        $rows = $query->get(['on_hand', 'reserved']);

        $onHand = (int) $rows->sum(fn (VariantInventory $row) => (int) $row->on_hand);
        $reserved = (int) $rows->sum(fn (VariantInventory $row) => (int) $row->reserved);

        return [
            'warehouse_code' => $warehouse->value,
            'on_hand' => $onHand,
            'reserved' => $reserved,
            'available' => max(0, $onHand - $reserved),
            'sku_count' => $rows->count(),
        ];
    }
}
