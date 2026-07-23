<?php

namespace App\Services\Inventory;

use App\Enums\InventoryMutationKind;
use App\Models\Admin;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\VariantInventory;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * ADR-055 Phase 2A-3C-4 — Admin inventory application service.
 *
 * Row lifecycle (is_active, warehouse, thresholds) may be written directly.
 * Every sellable / reserved quantity change goes through InventoryMutationGate.
 */
final class AdminInventoryApplicationService
{
    public function __construct(
        private readonly InventoryMutationGate $gate,
        private readonly CanonicalVariantInventoryInitializer $canonicalInitializer,
    ) {}

    /**
     * Bootstrap a VariantInventory row without shadowing positive legacy stock (RC1-B1).
     *
     * @param  array{
     *     warehouse_code?: string,
     *     on_hand?: int,
     *     reserved?: int,
     *     reorder_level?: int,
     *     safety_stock?: int,
     *     is_active?: bool,
     *     idempotency_key?: string|null
     * }  $data
     */
    public function createVariantInventory(
        ProductVariant $variant,
        array $data,
        ?Admin $actor = null,
    ): VariantInventory {
        $warehouse = strtoupper((string) ($data['warehouse_code'] ?? 'MAIN'));
        $requested = array_key_exists('on_hand', $data) ? max(0, (int) $data['on_hand']) : null;
        $targetReserved = max(0, (int) ($data['reserved'] ?? 0));
        $wantActive = (bool) ($data['is_active'] ?? true);

        // Pre-validate reserved against resolved opening qty (legacy inherit or explicit).
        $legacyQty = $this->canonicalInitializer->legacyQuantity($variant);
        $opening = $this->canonicalInitializer->resolveOpeningQuantity($requested, $legacyQty);
        if ($targetReserved > $opening) {
            throw ValidationException::withMessages([
                'reserved' => ['Reserved cannot exceed on hand.'],
            ]);
        }

        return $this->canonicalInitializer->ensure($variant, [
            'warehouse_code' => $warehouse,
            'requested_on_hand' => $requested,
            'reserved' => $targetReserved,
            'reorder_level' => (int) ($data['reorder_level'] ?? 5),
            'safety_stock' => (int) ($data['safety_stock'] ?? 0),
            'is_active' => $wantActive,
            'actor' => $actor,
            'idempotency_key' => isset($data['idempotency_key']) && is_string($data['idempotency_key'])
                ? $data['idempotency_key']
                : null,
            'reason' => 'Admin variant inventory create — opening stock',
        ]);
    }

    /**
     * Update metadata directly; route on_hand / reserved deltas through the gate.
     *
     * @param  array{
     *     warehouse_code?: string,
     *     on_hand?: int,
     *     reserved?: int,
     *     reserve?: int,
     *     release?: int,
     *     reorder_level?: int,
     *     safety_stock?: int,
     *     is_active?: bool,
     *     idempotency_key?: string|null
     * }  $data
     */
    public function updateVariantInventory(
        VariantInventory $inventory,
        array $data,
        ?Admin $actor = null,
    ): VariantInventory {
        return DB::transaction(function () use ($inventory, $data, $actor) {
            /** @var VariantInventory $locked */
            $locked = VariantInventory::query()->whereKey($inventory->id)->lockForUpdate()->firstOrFail();

            $idempotencyKey = isset($data['idempotency_key']) && is_string($data['idempotency_key'])
                ? $data['idempotency_key']
                : null;

            $currentOnHand = (int) $locked->on_hand;
            $currentReserved = (int) $locked->reserved;

            $targetOnHand = array_key_exists('on_hand', $data)
                ? max(0, (int) $data['on_hand'])
                : $currentOnHand;

            $targetReserved = array_key_exists('reserved', $data)
                ? max(0, (int) $data['reserved'])
                : $currentReserved;

            if (array_key_exists('reserve', $data)) {
                $targetReserved += max(0, (int) $data['reserve']);
            }
            if (array_key_exists('release', $data)) {
                $targetReserved = max(0, $targetReserved - max(0, (int) $data['release']));
            }

            if ($targetReserved > $targetOnHand) {
                throw ValidationException::withMessages([
                    array_key_exists('reserve', $data) ? 'reserve' : 'reserved' => ['Reserved cannot exceed on hand.'],
                ]);
            }

            // 1) Release reserved first when shrinking holds (frees room before on_hand drop).
            if ($targetReserved < $currentReserved) {
                $releaseQty = $currentReserved - $targetReserved;
                $this->gate->mutateReserved(
                    inventory: $locked,
                    kind: InventoryMutationKind::Release,
                    quantity: $releaseQty,
                    reason: 'Admin variant inventory update — release',
                    referenceType: VariantInventory::class,
                    referenceId: $locked->id,
                    metadata: ['source' => 'admin_variant_inventory_update', 'op' => 'release'],
                    idempotencyKey: $idempotencyKey !== null ? $idempotencyKey.':release' : null,
                    actor: $actor,
                );
                $locked = $locked->fresh() ?? $locked;
                $currentReserved = (int) $locked->reserved;
                $currentOnHand = (int) $locked->on_hand;
            }

            // 2) Absolute on_hand via Adjust delta (no artificial movement when unchanged).
            if ($targetOnHand !== $currentOnHand) {
                $delta = $targetOnHand - $currentOnHand;
                $kind = $delta > 0 ? InventoryMutationKind::Receive : InventoryMutationKind::Adjust;
                $this->gate->mutateVariantSellable(
                    inventory: $locked,
                    kind: $kind,
                    quantityChange: $delta,
                    actor: $actor,
                    reason: $delta > 0
                        ? 'Admin variant inventory update — increase stock'
                        : 'Admin variant inventory update — set stock (delta)',
                    referenceType: VariantInventory::class,
                    referenceId: $locked->id,
                    metadata: [
                        'source' => 'admin_variant_inventory_update',
                        'op' => 'set_on_hand',
                        'target_on_hand' => $targetOnHand,
                    ],
                    adjustSubtype: $delta < 0 ? 'correction' : null,
                    idempotencyKey: $idempotencyKey !== null ? $idempotencyKey.':on_hand' : null,
                );
                $locked = $locked->fresh() ?? $locked;
                $currentOnHand = (int) $locked->on_hand;
                $currentReserved = (int) $locked->reserved;
            }

            // 3) Increase reserved after on_hand is at target.
            if ($targetReserved > $currentReserved) {
                $reserveQty = $targetReserved - $currentReserved;
                $this->gate->mutateReserved(
                    inventory: $locked,
                    kind: InventoryMutationKind::Reserve,
                    quantity: $reserveQty,
                    reason: 'Admin variant inventory update — reserve',
                    referenceType: VariantInventory::class,
                    referenceId: $locked->id,
                    metadata: ['source' => 'admin_variant_inventory_update', 'op' => 'reserve'],
                    idempotencyKey: $idempotencyKey !== null ? $idempotencyKey.':reserve' : null,
                    actor: $actor,
                );
                $locked = $locked->fresh() ?? $locked;
            }

            // 4) Row lifecycle / metadata (not quantity).
            $meta = [];
            if (array_key_exists('warehouse_code', $data)) {
                $meta['warehouse_code'] = strtoupper((string) $data['warehouse_code']);
            }
            if (array_key_exists('reorder_level', $data)) {
                $meta['reorder_level'] = (int) $data['reorder_level'];
            }
            if (array_key_exists('safety_stock', $data)) {
                $meta['safety_stock'] = (int) $data['safety_stock'];
            }
            if (array_key_exists('is_active', $data)) {
                $meta['is_active'] = (bool) $data['is_active'];
            }

            if ($meta !== []) {
                $locked->forceFill($meta)->save();
            }

            return $locked->fresh() ?? $locked;
        });
    }

    /**
     * Soft-delete inventory row (lifecycle). Does not invent quantity mutations.
     */
    public function deleteVariantInventory(VariantInventory $inventory): void
    {
        DB::transaction(function () use ($inventory): void {
            /** @var VariantInventory $locked */
            $locked = VariantInventory::query()->whereKey($inventory->id)->lockForUpdate()->firstOrFail();
            $locked->delete();
        });
    }

    /**
     * Set simple (product-level) Catalog Stock to an absolute quantity via gate delta.
     * Bootstraps a zero row without ledger when creating for the first time at qty 0.
     */
    public function setSimpleProductStock(
        Product $product,
        int $targetQuantity,
        ?Admin $actor = null,
        ?string $reason = null,
        ?string $idempotencyKey = null,
        ?string $productVariantId = null,
    ): Inventory {
        $targetQuantity = max(0, $targetQuantity);

        return DB::transaction(function () use (
            $product,
            $targetQuantity,
            $reason,
            $idempotencyKey,
            $productVariantId,
        ) {
            $inventory = Inventory::query()
                ->where('product_id', $product->id)
                ->when(
                    $productVariantId === null,
                    fn ($q) => $q->whereNull('product_variant_id'),
                    fn ($q) => $q->where('product_variant_id', $productVariantId),
                )
                ->lockForUpdate()
                ->first();

            if ($inventory === null) {
                $inventory = Inventory::query()->create([
                    'product_id' => $product->id,
                    'product_variant_id' => $productVariantId,
                    'quantity' => 0,
                    'reserved_quantity' => 0,
                ]);

                if ($targetQuantity === 0) {
                    return $inventory;
                }
            }

            /** @var Inventory $locked */
            $locked = Inventory::query()->whereKey($inventory->id)->lockForUpdate()->firstOrFail();
            $current = (int) $locked->quantity;
            $delta = $targetQuantity - $current;

            if ($delta === 0) {
                return $locked;
            }

            $kind = $delta > 0 ? InventoryMutationKind::Receive : InventoryMutationKind::Adjust;

            $this->gate->mutateSimple(
                inventory: $locked,
                kind: $kind,
                quantityChange: $delta,
                reason: $reason ?? ($idempotencyKey ?? 'Admin simple stock set'),
                adjustSubtype: $delta < 0 ? 'correction' : null,
                idempotencyKey: $idempotencyKey,
            );

            return $locked->fresh() ?? $locked;
        });
    }
}
