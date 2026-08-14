<?php

namespace App\Services\Inventory;

use App\Enums\PurchasabilityPath;
use App\Enums\InventoryWarehouseCode;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\VariantInventory;
use App\Services\China\Procurement\ChinaCommercialStockService;
use App\Services\Commerce\CommerceChannelResolver;
use App\Services\Inventory\DTOs\StockResolutionContext;
use App\Services\Inventory\DTOs\StockResolutionResult;

/**
 * Canonical Catalog Stock read resolver (ADR 055 / Phase 2A-3B-1).
 *
 * Path-aligned with ADR 053:
 *   Simple  → inventory (product_variant_id NULL)
 *   Variant → variant_inventories (default warehouse MAIN)
 *
 * Read-only. Mutations remain on existing writers / InventoryControlEngine.
 * Callers select Simple vs Variant (usually via ProductPurchasabilityPolicy).
 */
final class StockResolver
{
    public function __construct(
        private readonly CommerceChannelResolver $commerceChannels,
        private readonly ChinaCommercialStockService $commercialStock,
        private readonly TzLocalInventoryScope $tzLocalScope,
    ) {}

    /**
     * Resolve Catalog Stock for an explicit sell unit.
     * Pass $variant for Variant path; omit for Simple (base) path.
     */
    public function resolve(
        Product $product,
        ?ProductVariant $variant = null,
        ?StockResolutionContext $context = null,
    ): StockResolutionResult {
        $context ??= new StockResolutionContext;

        if ($variant !== null) {
            return $this->resolveVariantProduct($variant, $context, $product);
        }

        return $this->resolveSimpleProduct($product, $context);
    }

    /**
     * Simple Product Catalog Stock SSoT: inventory (null variant).
     */
    public function resolveSimpleProduct(
        Product $product,
        ?StockResolutionContext $context = null,
    ): StockResolutionResult {
        $context ??= new StockResolutionContext;

        $product->loadMissing('commerceChannel');

        if ($this->commerceChannels->isChinaImportProduct($product)) {
            return $this->resolveCommercialStock($product, null, PurchasabilityPath::Simple, $context);
        }

        $row = $this->findSimpleInventory($product);

        if ($row === null) {
            return StockResolutionResult::unresolved(
                path: PurchasabilityPath::Simple,
                source: 'inventory',
                inventoryType: 'simple',
                meta: [
                    'product_id' => $product->id,
                    'policy_present' => false,
                ],
            );
        }

        $onHand = (int) $row->quantity;
        $reserved = (int) $row->reserved_quantity;
        $available = $context->includeReservations
            ? $row->availableQuantity()
            : max(0, $onHand);

        return new StockResolutionResult(
            resolved: true,
            source: 'inventory',
            inventoryType: 'simple',
            quantityOnHand: $onHand,
            quantityReserved: $reserved,
            quantityAvailable: $available,
            location: $row->warehouse_location !== null && $row->warehouse_location !== ''
                ? (string) $row->warehouse_location
                : null,
            path: PurchasabilityPath::Simple,
            inventory: $row,
            meta: [
                'product_id' => $product->id,
                'inventory_id' => $row->id,
                'policy_present' => true,
                // Reserved extension placeholders (no-ops in 2A-3B-1).
                'reservation_applied' => false,
                'warehouse_allocation' => null,
                'location_selection' => null,
                'channel_selection' => $context->channel,
            ],
        );
    }

    /**
     * Variant Product Catalog Stock SSoT: variant_inventories (MAIN by default).
     */
    public function resolveVariantProduct(
        ProductVariant $variant,
        ?StockResolutionContext $context = null,
        ?Product $product = null,
    ): StockResolutionResult {
        $context ??= new StockResolutionContext;
        $warehouse = $context->warehouseCode();
        $productId = $product?->id ?? $variant->product_id;
        $product ??= $variant->relationLoaded('product')
            ? $variant->product
            : Product::query()->find($productId);

        if ($product !== null) {
            $product->loadMissing('commerceChannel', 'store');
        }

        if ($product !== null && $this->commerceChannels->isChinaImportProduct($product)) {
            return $this->resolveCommercialStock($product, $variant, PurchasabilityPath::Variant, $context);
        }

        $warehouse = $product !== null
            ? $this->tzLocalScope->resolveCommerceWarehouse($product, $context->warehouseCode())
            : $context->warehouseCode();

        // Customer commerce sellable stock is MAIN-only (China import). TZ store codes are sellable for TZ_LOCAL.
        if ($context->commerceSellableOnly && ! $this->isSellableCommerceWarehouse($product, $warehouse)) {
            return StockResolutionResult::unresolved(
                path: PurchasabilityPath::Variant,
                source: 'variant_inventories',
                inventoryType: 'variant',
                meta: [
                    'product_id' => $productId,
                    'product_variant_id' => $variant->id,
                    'warehouse_code' => $warehouse,
                    'policy_present' => false,
                    'non_sellable_warehouse' => true,
                    'sellable_for_commerce' => false,
                ],
            );
        }

        $row = $this->findVariantInventory($variant, $warehouse, $product);

        if ($row === null
            && $product !== null
            && $this->tzLocalScope->appliesTo($product)
            && $warehouse !== 'MAIN') {
            $row = $this->findVariantInventory($variant, 'MAIN', $product);
        }

        if ($row === null) {
            // Inactive/invalid canonical row at this warehouse wins: never fall back to legacy.
            if ($this->hasWarehouseVariantInventory($variant, $warehouse)) {
                return StockResolutionResult::unresolved(
                    path: PurchasabilityPath::Variant,
                    source: 'variant_inventories',
                    inventoryType: 'variant',
                    meta: [
                        'product_id' => $productId,
                        'product_variant_id' => $variant->id,
                        'warehouse_code' => $warehouse,
                        'policy_present' => false,
                        'inactive_canonical' => true,
                    ],
                );
            }

            $legacy = $this->findLegacyVariantInventory($variant, $productId);
            if ($legacy !== null) {
                $onHand = (int) $legacy->quantity;
                $reserved = (int) $legacy->reserved_quantity;
                $available = $context->includeReservations
                    ? $legacy->availableQuantity()
                    : max(0, $onHand);

                return new StockResolutionResult(
                    resolved: true,
                    source: 'inventory',
                    inventoryType: 'variant_legacy',
                    quantityOnHand: $onHand,
                    quantityReserved: $reserved,
                    quantityAvailable: $available,
                    location: $legacy->warehouse_location !== null && $legacy->warehouse_location !== ''
                        ? (string) $legacy->warehouse_location
                        : null,
                    path: PurchasabilityPath::Variant,
                    inventory: $legacy,
                    meta: [
                        'product_id' => $productId,
                        'product_variant_id' => $variant->id,
                        'inventory_id' => $legacy->id,
                        'warehouse_code' => $warehouse,
                        'policy_present' => true,
                        'legacy_fallback' => true,
                        'reservation_applied' => false,
                        'warehouse_allocation' => null,
                        'location_selection' => $context->inventoryLocationId,
                        'channel_selection' => $context->channel,
                    ],
                );
            }

            return StockResolutionResult::unresolved(
                path: PurchasabilityPath::Variant,
                source: 'variant_inventories',
                inventoryType: 'variant',
                meta: [
                    'product_id' => $productId,
                    'product_variant_id' => $variant->id,
                    'warehouse_code' => $warehouse,
                    'policy_present' => false,
                ],
            );
        }

        $onHand = (int) $row->on_hand;
        $reserved = (int) $row->reserved;
        $available = $context->includeReservations
            ? $row->available()
            : max(0, $onHand);

        return new StockResolutionResult(
            resolved: true,
            source: 'variant_inventories',
            inventoryType: 'variant',
            quantityOnHand: $onHand,
            quantityReserved: $reserved,
            quantityAvailable: $available,
            location: (string) $row->warehouse_code,
            path: PurchasabilityPath::Variant,
            inventory: $row,
            meta: [
                'product_id' => $productId,
                'product_variant_id' => $variant->id,
                'variant_inventory_id' => $row->id,
                'warehouse_code' => $row->warehouse_code,
                'inventory_location_id' => $row->inventory_location_id,
                'is_active' => (bool) $row->is_active,
                'policy_present' => true,
                'sellable_for_commerce' => InventoryWarehouseCode::isSellableCommerceCode((string) $row->warehouse_code),
                'reservation_applied' => false,
                'warehouse_allocation' => null,
                'location_selection' => $context->inventoryLocationId,
                'channel_selection' => $context->channel,
            ],
        );
    }

    /**
     * ADR 053 inventory policy presence (row exists — quantity may be zero).
     */
    public function hasSimpleInventoryPolicy(Product $product): bool
    {
        return $this->resolveSimpleProduct($product)->hasInventoryPolicy();
    }

    /**
     * ADR 053 sellable-variant inventory policy: active MAIN (or context warehouse) row.
     */
    public function hasVariantInventoryPolicy(
        ProductVariant $variant,
        ?StockResolutionContext $context = null,
        ?Product $product = null,
    ): bool {
        return $this->resolveVariantProduct($variant, $context, $product)->hasInventoryPolicy();
    }

    private function findSimpleInventory(Product $product): ?Inventory
    {
        if ($product->relationLoaded('inventory')) {
            $row = $product->inventory->first(
                fn (Inventory $row) => $row->product_variant_id === null,
            );

            if ($row !== null) {
                return $row;
            }
        }

        return Inventory::query()
            ->where('product_id', $product->id)
            ->whereNull('product_variant_id')
            ->first();
    }

    private function findVariantInventory(ProductVariant $variant, string $warehouse, ?Product $product = null): ?VariantInventory
    {
        if ($variant->relationLoaded('inventories')) {
            $row = $variant->inventories->first(
                fn ($row) => $this->inventoryRowMatchesWarehouse($row, $warehouse, $product) && $row->is_active,
            );

            if ($row !== null) {
                return $row;
            }
        }

        $row = $variant->inventories()
            ->where('is_active', true)
            ->where(function ($query) use ($warehouse, $product) {
                $query->where('warehouse_code', $warehouse);
                if ($product !== null && $this->tzLocalScope->appliesTo($product)) {
                    $location = $this->tzLocalScope->storeLocation($product);
                    if ($location !== null) {
                        $query->orWhere('inventory_location_id', $location->id);
                    }
                }
            })
            ->first();

        if ($row !== null) {
            return $row;
        }

        // Preserve ResolveCartPurchasable fallback: mainInventory() when collection miss (China / legacy).
        if ($warehouse === 'MAIN') {
            return $variant->mainInventory();
        }

        return null;
    }

    private function inventoryRowMatchesWarehouse(VariantInventory $row, string $warehouse, ?Product $product): bool
    {
        if ($row->warehouse_code === $warehouse) {
            return true;
        }

        if ($product !== null && $this->tzLocalScope->appliesTo($product)) {
            $location = $this->tzLocalScope->storeLocation($product);

            return $location !== null
                && (string) $row->inventory_location_id === (string) $location->id;
        }

        return false;
    }

    private function isSellableCommerceWarehouse(?Product $product, string $warehouse): bool
    {
        if (InventoryWarehouseCode::isSellableCommerceCode($warehouse)) {
            return true;
        }

        return $product !== null && $this->tzLocalScope->isStoreCommerceWarehouse($product, $warehouse);
    }

    private function hasWarehouseVariantInventory(ProductVariant $variant, string $warehouse): bool
    {
        if ($variant->relationLoaded('inventories')) {
            return $variant->inventories->contains(
                fn ($row) => $row->warehouse_code === $warehouse,
            );
        }

        return $variant->inventories()
            ->where('warehouse_code', $warehouse)
            ->exists();
    }

    /**
     * Legacy commerce inventory row for a sellable variant (pre–variant_inventories).
     * Used only when no Catalog Stock row exists for the warehouse (including inactive).
     */
    private function findLegacyVariantInventory(ProductVariant $variant, string $productId): ?Inventory
    {
        if ($variant->relationLoaded('inventory') && $variant->inventory !== null) {
            return $variant->inventory;
        }

        return Inventory::query()
            ->where('product_id', $productId)
            ->where('product_variant_id', $variant->id)
            ->first();
    }

    private function resolveCommercialStock(
        Product $product,
        ?ProductVariant $variant,
        PurchasabilityPath $path,
        StockResolutionContext $context,
    ): StockResolutionResult {
        $row = $this->commercialStock->findForProduct($product, $variant);

        if ($row === null) {
            return StockResolutionResult::unresolved(
                path: $path,
                source: 'china_commercial_stocks',
                inventoryType: 'commercial',
                meta: [
                    'product_id' => $product->id,
                    'product_variant_id' => $variant?->id,
                    'policy_present' => false,
                    'inventory_source' => 'commercial',
                    'fulfillment_source' => 'china_import',
                    'channel_selection' => $context->channel,
                ],
            );
        }

        $available = (int) $row->available_quantity;
        $reserved = (int) $row->reserved_quantity;
        $ordered = (int) $row->ordered_quantity;

        return new StockResolutionResult(
            resolved: true,
            source: 'china_commercial_stocks',
            inventoryType: 'commercial',
            quantityOnHand: $available + $reserved,
            quantityReserved: $reserved,
            quantityAvailable: $available,
            location: 'COMMERCIAL',
            path: $path,
            inventory: null,
            meta: [
                'product_id' => $product->id,
                'product_variant_id' => $variant?->id,
                'china_commercial_stock_id' => $row->id,
                'ordered_quantity' => $ordered,
                'policy_present' => true,
                'inventory_source' => 'commercial',
                'fulfillment_source' => 'china_import',
                'sellable_for_commerce' => true,
                'reservation_applied' => false,
                'warehouse_allocation' => null,
                'location_selection' => $context->inventoryLocationId,
                'channel_selection' => $context->channel,
            ],
        );
    }
}
