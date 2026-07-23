<?php

namespace App\Services\Inventory;

use App\Enums\InventoryMutationKind;
use App\Models\Admin;
use App\Models\Inventory;
use App\Models\ProductVariant;
use App\Models\VariantInventory;
use Illuminate\Support\Facades\DB;

/**
 * RC1-B1 — Canonical MAIN VariantInventory initialization.
 *
 * Prevents active MAIN on_hand=0 from silently shadowing positive legacy inventory.quantity.
 * StockResolver is unchanged: once an active MAIN row exists, it remains authoritative.
 *
 * Opening stock for a newly authoritative MAIN is applied via InventoryMutationGate (Receive).
 * Legacy inventory rows are left intact for compatibility (not deleted / not zeroed).
 */
final class CanonicalVariantInventoryInitializer
{
    public function __construct(
        private readonly InventoryMutationGate $gate,
    ) {}

    /**
     * Ensure an active VariantInventory exists for the warehouse without shadowing legacy stock.
     *
     * @param  array{
     *     warehouse_code?: string,
     *     inventory_location_id?: string|null,
     *     requested_on_hand?: int|null,
     *     reserved?: int,
     *     reorder_level?: int,
     *     safety_stock?: int,
     *     is_active?: bool,
     *     actor?: Admin|null,
     *     idempotency_key?: string|null,
     *     reason?: string|null,
     * }  $options
     *
     * requested_on_hand:
     * - null (omitted): inherit positive legacy quantity when establishing MAIN
     * - > 0: use that quantity only (never legacy + requested)
     * - 0 on first establish: still inherit positive legacy (avoid false OOS shadow)
     */
    public function ensure(ProductVariant $variant, array $options = []): VariantInventory
    {
        return DB::transaction(function () use ($variant, $options) {
            $warehouse = strtoupper((string) ($options['warehouse_code'] ?? 'MAIN'));
            $locationId = $options['inventory_location_id'] ?? null;
            $actor = $options['actor'] ?? null;
            $wantActive = (bool) ($options['is_active'] ?? true);
            $reorder = (int) ($options['reorder_level'] ?? 5);
            $safety = (int) ($options['safety_stock'] ?? 0);
            $targetReserved = max(0, (int) ($options['reserved'] ?? 0));
            $idempotencyKey = isset($options['idempotency_key']) && is_string($options['idempotency_key'])
                ? $options['idempotency_key']
                : 'canonical-main-init:'.$variant->id.':'.$warehouse;
            $reason = $options['reason'] ?? 'Canonical MAIN initialization — opening stock from legacy';

            $requested = array_key_exists('requested_on_hand', $options)
                ? ($options['requested_on_hand'] === null ? null : max(0, (int) $options['requested_on_hand']))
                : null;

            /** @var VariantInventory|null $existing */
            $existing = VariantInventory::withTrashed()
                ->where('product_variant_id', $variant->id)
                ->where('warehouse_code', $warehouse)
                ->lockForUpdate()
                ->first();

            if ($existing !== null && ! $existing->trashed()) {
                // Established MAIN is authoritative — including legitimate zero.
                return $existing;
            }

            $legacyQty = $this->legacyQuantity($variant);
            $openingQty = $this->resolveOpeningQuantity($requested, $legacyQty);

            if ($existing !== null && $existing->trashed()) {
                $existing->restore();
                $existing->forceFill([
                    'on_hand' => 0,
                    'reserved' => 0,
                    'damaged' => 0,
                    'inspection' => 0,
                    'is_active' => $wantActive,
                    'inventory_location_id' => $locationId ?? $existing->inventory_location_id,
                    'warehouse_code' => $warehouse,
                    'reorder_level' => $reorder,
                    'safety_stock' => $safety,
                ])->save();
                $inventory = $existing->fresh() ?? $existing;
            } else {
                $inventory = VariantInventory::query()->create([
                    'product_variant_id' => $variant->id,
                    'inventory_location_id' => $locationId,
                    'warehouse_code' => $warehouse,
                    'on_hand' => 0,
                    'reserved' => 0,
                    'damaged' => 0,
                    'inspection' => 0,
                    'reorder_level' => $reorder,
                    'safety_stock' => $safety,
                    'is_active' => $wantActive,
                ]);
            }

            /** @var VariantInventory $locked */
            $locked = VariantInventory::query()->whereKey($inventory->id)->lockForUpdate()->firstOrFail();

            if ($openingQty > 0) {
                $this->gate->mutateVariantSellable(
                    inventory: $locked,
                    kind: InventoryMutationKind::Receive,
                    quantityChange: $openingQty,
                    actor: $actor instanceof Admin ? $actor : null,
                    reason: $reason,
                    referenceType: VariantInventory::class,
                    referenceId: $locked->id,
                    metadata: [
                        'source' => 'canonical_variant_inventory_initializer',
                        'op' => 'opening_receive',
                        'legacy_quantity' => $legacyQty,
                        'requested_on_hand' => $requested,
                        'warehouse_code' => $warehouse,
                    ],
                    idempotencyKey: $idempotencyKey.':opening_receive',
                );
                $locked = $locked->fresh() ?? $locked;
            }

            if ($targetReserved > 0) {
                $available = max(0, (int) $locked->on_hand - (int) $locked->reserved);
                $reserveQty = min($targetReserved, $available);
                if ($reserveQty > 0) {
                    $this->gate->mutateReserved(
                        inventory: $locked,
                        kind: InventoryMutationKind::Reserve,
                        quantity: $reserveQty,
                        reason: 'Canonical MAIN initialization — opening reserve',
                        referenceType: VariantInventory::class,
                        referenceId: $locked->id,
                        metadata: [
                            'source' => 'canonical_variant_inventory_initializer',
                            'op' => 'opening_reserve',
                        ],
                        idempotencyKey: $idempotencyKey.':opening_reserve',
                        actor: $actor instanceof Admin ? $actor : null,
                    );
                    $locked = $locked->fresh() ?? $locked;
                }
            }

            if ((bool) $locked->is_active !== $wantActive) {
                $locked->forceFill(['is_active' => $wantActive])->save();
            }

            return $locked->fresh() ?? $locked;
        });
    }

    /**
     * Opening quantity when establishing MAIN authority for the first time (or after restore-to-zero).
     */
    public function resolveOpeningQuantity(?int $requestedOnHand, int $legacyQuantity): int
    {
        $legacyQuantity = max(0, $legacyQuantity);

        if ($requestedOnHand !== null && $requestedOnHand > 0) {
            // Explicit positive admin target — never legacy + requested.
            return $requestedOnHand;
        }

        // Omitted or explicit zero on first establish: inherit positive legacy to avoid false OOS.
        return $legacyQuantity;
    }

    public function legacyQuantity(ProductVariant $variant): int
    {
        $row = Inventory::query()
            ->where('product_id', $variant->product_id)
            ->where('product_variant_id', $variant->id)
            ->lockForUpdate()
            ->first();

        return max(0, (int) ($row?->quantity ?? 0));
    }
}
