<?php

namespace App\Services\Inventory;

use App\Models\Inventory;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\VariantInventory;
use App\Services\Inventory\DTOs\StockResolutionContext;
use App\Services\Inventory\DTOs\StockResolutionResult;

/**
 * Present StockResolver results in existing API inventory contracts (ADR 055 / 2A-3C-1).
 * Does not read inventory tables directly — always via StockResolver.
 */
final class CatalogStockPresenter
{
    public function __construct(
        private readonly StockResolver $stockResolver,
    ) {}

    public function resolveForProduct(Product $product, ?ProductVariant $variant = null): StockResolutionResult
    {
        return $this->stockResolver->resolve($product, $variant);
    }

    public function availableForVariant(ProductVariant $variant, ?Product $product = null): int
    {
        return max(0, $this->stockResolver->resolveVariantProduct($variant, null, $product)->quantityAvailable);
    }

    public function availableForSimple(Product $product): int
    {
        return max(0, $this->stockResolver->resolveSimpleProduct($product)->quantityAvailable);
    }

    /**
     * Legacy InventoryResource-shaped payload (customer + admin variant/product APIs).
     *
     * @return array<string, mixed>|null
     */
    public function toInventoryContract(StockResolutionResult $stock): ?array
    {
        if (! $stock->resolved || $stock->inventory === null) {
            return null;
        }

        if ($stock->inventory instanceof Inventory) {
            return [
                'id' => $stock->inventory->id,
                'quantity' => $stock->quantityOnHand,
                'reserved_quantity' => $stock->quantityReserved,
                'available_quantity' => $stock->quantityAvailable,
                'low_stock_threshold' => (int) ($stock->inventory->low_stock_threshold ?? 0),
                'is_low_stock' => $stock->inventory->isLowStock(),
                'warehouse_location' => $stock->location,
            ];
        }

        if ($stock->inventory instanceof VariantInventory) {
            return [
                'id' => $stock->inventory->id,
                'quantity' => $stock->quantityOnHand,
                'reserved_quantity' => $stock->quantityReserved,
                'available_quantity' => $stock->quantityAvailable,
                'low_stock_threshold' => (int) ($stock->inventory->reorder_level ?? 0),
                'is_low_stock' => $stock->inventory->needsReorder(),
                'warehouse_location' => $stock->location,
            ];
        }

        return null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function simpleInventoryCollection(Product $product): array
    {
        $stock = $this->stockResolver->resolveSimpleProduct($product);
        $row = $this->toInventoryContract($stock);

        return $row === null ? [] : [$row];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function variantInventoryContract(ProductVariant $variant, ?Product $product = null): ?array
    {
        return $this->toInventoryContract(
            $this->stockResolver->resolveVariantProduct($variant, null, $product),
        );
    }
}
