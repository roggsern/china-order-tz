<?php

namespace App\Services\Inventory;

use App\Enums\InventoryMutationKind;
use App\Enums\PurchasabilityPath;
use App\Models\Admin;
use App\Models\Inventory;
use App\Models\InventoryLocation;
use App\Models\InventoryMovement;
use App\Models\InventoryStockMovement;
use App\Models\VariantInventory;
use App\Services\Inventory\DTOs\InventoryMutationContext;
use App\Services\Inventory\DTOs\InventoryMutationResult;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Canonical inventory write gate (ADR 055 / Phase 2A-3B-2).
 *
 * Opens a transaction, locks the Catalog Stock row, applies the delta,
 * appends the expected ledger movement, and returns a structured result.
 *
 * Variant path → variant_inventories + inventory_stock_movements
 * Simple path  → inventory + inventory_movements
 *
 * Does not implement reservation or payment commitment.
 */
final class InventoryMutationGate
{
    /**
     * Apply a mutation described by context.
     * Prefer typed helpers (mutateVariantSellable / mutateSimple) from engine facades.
     */
    public function apply(InventoryMutationContext $context): InventoryMutationResult
    {
        if ($context->isReservation || $context->isCommitment) {
            throw ValidationException::withMessages([
                'mutation' => ['Reservation and commitment mutations are not enabled in this sprint.'],
            ]);
        }

        $inventory = $context->inventory;

        if ($inventory instanceof VariantInventory) {
            return $this->mutateVariantSellable(
                inventory: $inventory,
                kind: $context->kind,
                quantityChange: $context->quantityChange,
                actor: $context->actor,
                reason: $context->reason,
                referenceType: $context->referenceType,
                referenceId: $context->referenceId,
                metadata: $this->mergeMetadata($context),
                damagedDelta: $context->damagedDelta,
                inspectionDelta: $context->inspectionDelta,
                adjustSubtype: $context->adjustSubtype,
                idempotencyKey: $context->idempotencyKey,
            );
        }

        if ($inventory instanceof Inventory) {
            return $this->mutateSimple(
                inventory: $inventory,
                kind: $context->kind,
                quantityChange: $context->quantityChange,
                reason: $context->reason,
                adjustSubtype: $context->adjustSubtype,
                idempotencyKey: $context->idempotencyKey,
            );
        }

        throw ValidationException::withMessages([
            'inventory' => ['An inventory row is required for mutation.'],
        ]);
    }

    /**
     * Variant Catalog Stock mutation — preserves InventoryControlEngine::mutateSellable behavior.
     *
     * @param  array<string, mixed>|null  $metadata
     */
    public function mutateVariantSellable(
        VariantInventory $inventory,
        InventoryMutationKind $kind,
        int $quantityChange,
        ?Admin $actor = null,
        ?string $reason = null,
        ?string $referenceType = null,
        ?string $referenceId = null,
        ?array $metadata = null,
        int $damagedDelta = 0,
        int $inspectionDelta = 0,
        ?string $adjustSubtype = null,
        ?string $idempotencyKey = null,
    ): InventoryMutationResult {
        $movementType = $kind->toVariantMovementType($adjustSubtype);

        return DB::transaction(function () use (
            $inventory,
            $kind,
            $movementType,
            $quantityChange,
            $actor,
            $reason,
            $referenceType,
            $referenceId,
            $metadata,
            $damagedDelta,
            $inspectionDelta,
            $idempotencyKey,
        ) {
            if ($idempotencyKey !== null) {
                $existing = InventoryStockMovement::query()
                    ->where('variant_inventory_id', $inventory->id)
                    ->where('reference_type', $referenceType)
                    ->where('reference_id', $referenceId)
                    ->where('movement_type', $movementType)
                    ->get()
                    ->first(fn (InventoryStockMovement $m) => ($m->metadata['idempotency_key'] ?? null) === $idempotencyKey);

                if ($existing !== null) {
                    return new InventoryMutationResult(
                        applied: true,
                        kind: $kind,
                        path: PurchasabilityPath::Variant,
                        source: 'variant_inventories',
                        quantityBefore: (int) $existing->quantity_before,
                        quantityChange: (int) $existing->quantity_change,
                        quantityAfter: (int) $existing->quantity_after,
                        inventory: $inventory->fresh() ?? $inventory,
                        movement: $existing,
                        idempotentReplay: true,
                        meta: ['idempotency_key' => $idempotencyKey],
                    );
                }
            }

            /** @var VariantInventory $locked */
            $locked = VariantInventory::query()->whereKey($inventory->id)->lockForUpdate()->firstOrFail();

            $before = (int) $locked->on_hand;
            $after = $before + $quantityChange;
            if ($after < 0) {
                throw ValidationException::withMessages([
                    'quantity' => ['Insufficient sellable stock for this operation.'],
                ]);
            }

            $damaged = max(0, (int) $locked->damaged + $damagedDelta);
            $inspection = max(0, (int) $locked->inspection + $inspectionDelta);

            $locked->forceFill([
                'on_hand' => $after,
                'damaged' => $damaged,
                'inspection' => $inspection,
                'is_active' => true,
            ])->save();

            $storeId = $locked->inventoryLocation?->store_id
                ?? InventoryLocation::query()->whereKey($locked->inventory_location_id)->value('store_id');

            $meta = $metadata ?? [];
            if ($idempotencyKey !== null) {
                $meta['idempotency_key'] = $idempotencyKey;
            }

            $movement = InventoryStockMovement::query()->create([
                'variant_inventory_id' => $locked->id,
                'product_variant_id' => $locked->product_variant_id,
                'inventory_location_id' => $locked->inventory_location_id,
                'store_id' => $storeId,
                'movement_type' => $movementType,
                'quantity_before' => $before,
                'quantity_change' => $quantityChange,
                'quantity_after' => $after,
                'damaged_after' => $damaged,
                'inspection_after' => $inspection,
                'reason' => $reason,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'actor_type' => $actor ? 'admin' : 'system',
                'actor_id' => $actor?->id,
                'metadata' => $meta === [] ? null : $meta,
                'created_at' => now(),
            ]);

            return new InventoryMutationResult(
                applied: true,
                kind: $kind,
                path: PurchasabilityPath::Variant,
                source: 'variant_inventories',
                quantityBefore: $before,
                quantityChange: $quantityChange,
                quantityAfter: $after,
                inventory: $locked,
                movement: $movement,
                idempotentReplay: false,
                meta: [
                    'movement_type' => $movementType->value,
                    'damaged_after' => $damaged,
                    'inspection_after' => $inspection,
                ],
            );
        });
    }

    /**
     * Simple Catalog Stock mutation — inventory + inventory_movements.
     * Infrastructure for ADR-055 Simple path; online/admin pay writers not migrated yet.
     */
    public function mutateSimple(
        Inventory $inventory,
        InventoryMutationKind $kind,
        int $quantityChange,
        ?string $reason = null,
        ?string $adjustSubtype = null,
        ?string $idempotencyKey = null,
    ): InventoryMutationResult {
        $type = $kind->toSimpleMovementType($adjustSubtype);

        return DB::transaction(function () use (
            $inventory,
            $kind,
            $type,
            $quantityChange,
            $reason,
            $idempotencyKey,
        ) {
            /** @var Inventory $locked */
            $locked = Inventory::query()->whereKey($inventory->id)->lockForUpdate()->firstOrFail();

            if ($idempotencyKey !== null) {
                $existing = InventoryMovement::query()
                    ->where('inventory_id', $locked->id)
                    ->where('type', $type)
                    ->where('reason', $reason)
                    ->where('quantity', $quantityChange)
                    ->first();

                if ($existing !== null) {
                    return new InventoryMutationResult(
                        applied: true,
                        kind: $kind,
                        path: PurchasabilityPath::Simple,
                        source: 'inventory',
                        quantityBefore: (int) $locked->quantity - $quantityChange,
                        quantityChange: $quantityChange,
                        quantityAfter: (int) $locked->quantity,
                        inventory: $locked,
                        movement: $existing,
                        idempotentReplay: true,
                        meta: ['idempotency_key' => $idempotencyKey],
                    );
                }
            }

            $before = (int) $locked->quantity;
            $after = $before + $quantityChange;
            if ($after < 0) {
                throw ValidationException::withMessages([
                    'quantity' => ['Insufficient sellable stock for this operation.'],
                ]);
            }

            $locked->forceFill(['quantity' => $after])->save();

            $movement = $locked->movements()->create([
                'quantity' => $quantityChange,
                'type' => $type,
                'reason' => $reason,
            ]);

            return new InventoryMutationResult(
                applied: true,
                kind: $kind,
                path: PurchasabilityPath::Simple,
                source: 'inventory',
                quantityBefore: $before,
                quantityChange: $quantityChange,
                quantityAfter: $after,
                inventory: $locked,
                movement: $movement,
                idempotentReplay: false,
                meta: ['movement_type' => $type],
            );
        });
    }

    /**
     * Map InventoryMovementType-driven engine calls onto gate kinds.
     */
    public static function kindFromMovementType(\App\Enums\InventoryMovementType $type): InventoryMutationKind
    {
        return match ($type) {
            \App\Enums\InventoryMovementType::Receive => InventoryMutationKind::Receive,
            \App\Enums\InventoryMovementType::Sale => InventoryMutationKind::Sell,
            \App\Enums\InventoryMovementType::Return => InventoryMutationKind::Return,
            \App\Enums\InventoryMovementType::Damage => InventoryMutationKind::Damage,
            \App\Enums\InventoryMovementType::Adjustment,
            \App\Enums\InventoryMovementType::Correction => InventoryMutationKind::Adjust,
        };
    }

    /**
     * @return array<string, mixed>|null
     */
    private function mergeMetadata(InventoryMutationContext $context): ?array
    {
        $meta = $context->metadata ?? [];
        if ($context->idempotencyKey !== null) {
            $meta['idempotency_key'] = $context->idempotencyKey;
        }
        if ($context->channel !== null) {
            $meta['channel'] = $context->channel;
        }

        return $meta === [] ? null : $meta;
    }
}
