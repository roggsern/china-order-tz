<?php

namespace App\Services\Inventory;

use App\Enums\PurchasabilityPath;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\VariantInventory;
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

        $row = $this->findVariantInventory($variant, $warehouse);

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
    ): bool {
        return $this->resolveVariantProduct($variant, $context)->hasInventoryPolicy();
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

    private function findVariantInventory(ProductVariant $variant, string $warehouse): ?VariantInventory
    {
        if ($variant->relationLoaded('inventories')) {
            $row = $variant->inventories->first(
                fn ($row) => $row->warehouse_code === $warehouse && $row->is_active,
            );

            if ($row !== null) {
                return $row;
            }
        }

        $row = $variant->inventories()
            ->where('warehouse_code', $warehouse)
            ->where('is_active', true)
            ->first();

        if ($row !== null) {
            return $row;
        }

        // Preserve ResolveCartPurchasable fallback: mainInventory() when collection miss.
        if ($warehouse === 'MAIN') {
            return $variant->mainInventory();
        }

        return null;
    }

    /**
     * True when any Catalog Stock row exists for the warehouse (active or inactive).
     * Inactive rows block legacy fallback so canonical authority wins.
     */
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
}
