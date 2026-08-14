<?php

namespace App\Services\Inventory;

use App\Models\Inventory;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\VariantInventory;
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

    /**
     * Eager loads for customer listing cards (stock presentation without N+1).
     * Intentionally omits variant media/attributes — listing cards serialize slim stock only.
     *
     * @return array<string, mixed>
     */
    public static function catalogListingEagerLoads(): array
    {
        return [
            'inventory',
            // China simple-path stock (null variant) — used by StockResolver via findForProduct.
            'chinaCommercialStocks',
            'shippingOptions' => fn ($query) => $query->available(),
            'variants' => fn ($query) => $query
                ->where('is_active', true)
                ->with([
                    // prices: needed for purchasability path (not serialized on listing cards)
                    'prices',
                    'inventories',
                    'inventory',
                    'chinaCommercialStock',
                    // store_id required for TZ_LOCAL warehouse remap (TzLocalInventoryScope).
                    'product:id,slug,name,price,compare_at_price,fulfillment_source,commerce_channel_id,store_id',
                ]),
        ];
    }

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
    public function toInventoryContract(
        StockResolutionResult $stock,
        bool $includeWarehouseLocation = true,
    ): ?array {
        if (! $stock->resolved) {
            return null;
        }

        if ($stock->inventory === null && $stock->inventoryType === 'commercial') {
            return [
                'id' => $stock->meta['china_commercial_stock_id'] ?? null,
                'quantity' => $stock->quantityOnHand,
                'reserved_quantity' => $stock->quantityReserved,
                'available_quantity' => $stock->quantityAvailable,
                'low_stock_threshold' => 0,
                'is_low_stock' => false,
            ];
        }

        if ($stock->inventory === null) {
            return null;
        }

        if ($stock->inventory instanceof Inventory) {
            $contract = [
                'id' => $stock->inventory->id,
                'quantity' => $stock->quantityOnHand,
                'reserved_quantity' => $stock->quantityReserved,
                'available_quantity' => $stock->quantityAvailable,
                'low_stock_threshold' => (int) ($stock->inventory->low_stock_threshold ?? 0),
                'is_low_stock' => $stock->inventory->isLowStock(),
            ];

            if ($includeWarehouseLocation) {
                $contract['warehouse_location'] = $stock->location;
            }

            return $contract;
        }

        if ($stock->inventory instanceof VariantInventory) {
            $contract = [
                'id' => $stock->inventory->id,
                'quantity' => $stock->quantityOnHand,
                'reserved_quantity' => $stock->quantityReserved,
                'available_quantity' => $stock->quantityAvailable,
                'low_stock_threshold' => (int) ($stock->inventory->reorder_level ?? 0),
                'is_low_stock' => $stock->inventory->needsReorder(),
            ];

            if ($includeWarehouseLocation) {
                $contract['warehouse_location'] = $stock->location;
            }

            return $contract;
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
