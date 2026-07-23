<?php

namespace App\Services\Inventory;

use App\Enums\InventoryMovementType;
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
 * Does not implement payment commitment via apply(); reservations use mutateReserved().
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
     * Soft-hold mutation: adjusts reserved only (on_hand unchanged).
     * Reserve = +reserved; Release = −reserved. Used by ReservationService (ADR 055).
     *
     * @param  array<string, mixed>|null  $metadata
     */
    public function mutateReserved(
        Inventory|VariantInventory $inventory,
        InventoryMutationKind $kind,
        int $quantity,
        ?string $reason = null,
        ?string $referenceType = null,
        ?string $referenceId = null,
        ?array $metadata = null,
        ?string $idempotencyKey = null,
        ?Admin $actor = null,
    ): InventoryMutationResult {
        if (! in_array($kind, [InventoryMutationKind::Reserve, InventoryMutationKind::Release], true)) {
            throw ValidationException::withMessages([
                'kind' => ['mutateReserved requires Reserve or Release kind.'],
            ]);
        }

        if ($quantity < 1) {
            throw ValidationException::withMessages([
                'quantity' => ['Reservation quantity must be at least 1.'],
            ]);
        }

        $delta = $kind === InventoryMutationKind::Reserve ? $quantity : -1 * $quantity;

        if ($inventory instanceof VariantInventory) {
            return $this->mutateVariantReserved(
                $inventory,
                $kind,
                $delta,
                $reason,
                $referenceType,
                $referenceId,
                $metadata,
                $idempotencyKey,
                $actor,
            );
        }

        return $this->mutateSimpleReserved(
            $inventory,
            $kind,
            $delta,
            $reason,
            $idempotencyKey,
            $referenceType,
            $referenceId,
            $metadata,
        );
    }

    /**
     * Atomic reservation → sale: −reserved and −on_hand/quantity in one lock (ADR 055).
     *
     * @param  array<string, mixed>|null  $metadata
     */
    public function convertReservedToSale(
        Inventory|VariantInventory $inventory,
        int $quantity,
        ?string $reason = null,
        ?string $referenceType = null,
        ?string $referenceId = null,
        ?array $metadata = null,
        ?string $idempotencyKey = null,
        ?Admin $actor = null,
    ): InventoryMutationResult {
        if ($quantity < 1) {
            throw ValidationException::withMessages([
                'quantity' => ['Conversion quantity must be at least 1.'],
            ]);
        }

        if ($inventory instanceof VariantInventory) {
            return $this->convertVariantReservedToSale(
                $inventory,
                $quantity,
                $reason,
                $referenceType,
                $referenceId,
                $metadata,
                $idempotencyKey,
                $actor,
            );
        }

        return $this->convertSimpleReservedToSale(
            $inventory,
            $quantity,
            $reason,
            $idempotencyKey,
            $metadata,
        );
    }

    /**
     * @param  array<string, mixed>|null  $metadata
     */
    private function mutateVariantReserved(
        VariantInventory $inventory,
        InventoryMutationKind $kind,
        int $reservedDelta,
        ?string $reason,
        ?string $referenceType,
        ?string $referenceId,
        ?array $metadata,
        ?string $idempotencyKey,
        ?Admin $actor,
    ): InventoryMutationResult {
        $movementType = InventoryMovementType::Adjustment;

        return DB::transaction(function () use (
            $inventory,
            $kind,
            $reservedDelta,
            $reason,
            $referenceType,
            $referenceId,
            $metadata,
            $idempotencyKey,
            $actor,
            $movementType,
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
                        quantityChange: 0,
                        quantityAfter: (int) $existing->quantity_after,
                        inventory: $inventory->fresh() ?? $inventory,
                        movement: $existing,
                        idempotentReplay: true,
                        meta: [
                            'idempotency_key' => $idempotencyKey,
                            'reserved_delta' => $reservedDelta,
                            'reservation' => true,
                        ],
                    );
                }
            }

            /** @var VariantInventory $locked */
            $locked = VariantInventory::query()->whereKey($inventory->id)->lockForUpdate()->firstOrFail();

            $onHand = (int) $locked->on_hand;
            $reservedBefore = (int) $locked->reserved;
            $reservedAfter = $reservedBefore + $reservedDelta;

            if ($reservedAfter < 0) {
                throw ValidationException::withMessages([
                    'quantity' => ['Cannot release more than reserved quantity.'],
                ]);
            }

            if ($reservedDelta > 0 && ($onHand - $reservedBefore) < $reservedDelta) {
                throw ValidationException::withMessages([
                    'quantity' => ['Insufficient available stock to reserve.'],
                ]);
            }

            $locked->forceFill([
                'reserved' => $reservedAfter,
                'is_active' => true,
            ])->save();

            $storeId = $locked->inventoryLocation?->store_id
                ?? InventoryLocation::query()->whereKey($locked->inventory_location_id)->value('store_id');

            $meta = array_merge($metadata ?? [], [
                'reservation' => true,
                'reservation_op' => $kind->value,
                'reserved_before' => $reservedBefore,
                'reserved_after' => $reservedAfter,
                'reserved_delta' => $reservedDelta,
            ]);
            if ($idempotencyKey !== null) {
                $meta['idempotency_key'] = $idempotencyKey;
            }

            $movement = InventoryStockMovement::query()->create([
                'variant_inventory_id' => $locked->id,
                'product_variant_id' => $locked->product_variant_id,
                'inventory_location_id' => $locked->inventory_location_id,
                'store_id' => $storeId,
                'movement_type' => $movementType,
                'quantity_before' => $onHand,
                'quantity_change' => 0,
                'quantity_after' => $onHand,
                'damaged_after' => (int) $locked->damaged,
                'inspection_after' => (int) $locked->inspection,
                'reason' => $reason,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'actor_type' => $actor ? 'admin' : 'system',
                'actor_id' => $actor?->id,
                'metadata' => $meta,
                'created_at' => now(),
            ]);

            return new InventoryMutationResult(
                applied: true,
                kind: $kind,
                path: PurchasabilityPath::Variant,
                source: 'variant_inventories',
                quantityBefore: $onHand,
                quantityChange: 0,
                quantityAfter: $onHand,
                inventory: $locked,
                movement: $movement,
                idempotentReplay: false,
                meta: [
                    'movement_type' => $movementType->value,
                    'reserved_before' => $reservedBefore,
                    'reserved_after' => $reservedAfter,
                    'reserved_delta' => $reservedDelta,
                    'reservation' => true,
                ],
            );
        });
    }

    /**
     * @param  array<string, mixed>|null  $metadata
     */
    private function mutateSimpleReserved(
        Inventory $inventory,
        InventoryMutationKind $kind,
        int $reservedDelta,
        ?string $reason,
        ?string $idempotencyKey,
        ?string $referenceType = null,
        ?string $referenceId = null,
        ?array $metadata = null,
    ): InventoryMutationResult {
        $type = 'adjustment';
        $storedReason = $idempotencyKey !== null
            ? $idempotencyKey.($reason !== null && $reason !== '' ? '|'.$reason : '')
            : ($reason ?? 'reservation');

        return DB::transaction(function () use (
            $inventory,
            $kind,
            $reservedDelta,
            $storedReason,
            $idempotencyKey,
            $type,
            $referenceType,
            $referenceId,
            $metadata,
        ) {
            /** @var Inventory $locked */
            $locked = Inventory::query()->whereKey($inventory->id)->lockForUpdate()->firstOrFail();

            if ($idempotencyKey !== null) {
                $existing = InventoryMovement::query()
                    ->where('inventory_id', $locked->id)
                    ->where('type', $type)
                    ->where('reason', 'like', $idempotencyKey.'%')
                    ->first();

                if ($existing !== null) {
                    return new InventoryMutationResult(
                        applied: true,
                        kind: $kind,
                        path: PurchasabilityPath::Simple,
                        source: 'inventory',
                        quantityBefore: (int) $locked->quantity,
                        quantityChange: 0,
                        quantityAfter: (int) $locked->quantity,
                        inventory: $locked,
                        movement: $existing,
                        idempotentReplay: true,
                        meta: [
                            'idempotency_key' => $idempotencyKey,
                            'reserved_delta' => $reservedDelta,
                            'reservation' => true,
                            'reference_type' => $referenceType,
                            'reference_id' => $referenceId,
                        ],
                    );
                }
            }

            $onHand = (int) $locked->quantity;
            $reservedBefore = (int) $locked->reserved_quantity;
            $reservedAfter = $reservedBefore + $reservedDelta;

            if ($reservedAfter < 0) {
                throw ValidationException::withMessages([
                    'quantity' => ['Cannot release more than reserved quantity.'],
                ]);
            }

            if ($reservedDelta > 0 && ($onHand - $reservedBefore) < $reservedDelta) {
                throw ValidationException::withMessages([
                    'quantity' => ['Insufficient available stock to reserve.'],
                ]);
            }

            $locked->forceFill(['reserved_quantity' => $reservedAfter])->save();

            $movement = $locked->movements()->create([
                'quantity' => $reservedDelta,
                'type' => $type,
                'reason' => $storedReason,
            ]);

            return new InventoryMutationResult(
                applied: true,
                kind: $kind,
                path: PurchasabilityPath::Simple,
                source: 'inventory',
                quantityBefore: $onHand,
                quantityChange: 0,
                quantityAfter: $onHand,
                inventory: $locked,
                movement: $movement,
                idempotentReplay: false,
                meta: array_merge($metadata ?? [], [
                    'movement_type' => $type,
                    'reserved_before' => $reservedBefore,
                    'reserved_after' => $reservedAfter,
                    'reserved_delta' => $reservedDelta,
                    'reservation' => true,
                    'idempotency_key' => $idempotencyKey,
                    'reference_type' => $referenceType,
                    'reference_id' => $referenceId,
                ]),
            );
        });
    }

    /**
     * @param  array<string, mixed>|null  $metadata
     */
    private function convertVariantReservedToSale(
        VariantInventory $inventory,
        int $quantity,
        ?string $reason,
        ?string $referenceType,
        ?string $referenceId,
        ?array $metadata,
        ?string $idempotencyKey,
        ?Admin $actor,
    ): InventoryMutationResult {
        $movementType = InventoryMovementType::Sale;

        return DB::transaction(function () use (
            $inventory,
            $quantity,
            $reason,
            $referenceType,
            $referenceId,
            $metadata,
            $idempotencyKey,
            $actor,
            $movementType,
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
                        kind: InventoryMutationKind::Sell,
                        path: PurchasabilityPath::Variant,
                        source: 'variant_inventories',
                        quantityBefore: (int) $existing->quantity_before,
                        quantityChange: (int) $existing->quantity_change,
                        quantityAfter: (int) $existing->quantity_after,
                        inventory: $inventory->fresh() ?? $inventory,
                        movement: $existing,
                        idempotentReplay: true,
                        meta: [
                            'idempotency_key' => $idempotencyKey,
                            'reservation_convert' => true,
                        ],
                    );
                }
            }

            /** @var VariantInventory $locked */
            $locked = VariantInventory::query()->whereKey($inventory->id)->lockForUpdate()->firstOrFail();

            $onHandBefore = (int) $locked->on_hand;
            $reservedBefore = (int) $locked->reserved;

            if ($reservedBefore < $quantity) {
                throw ValidationException::withMessages([
                    'quantity' => ['Cannot convert more than reserved quantity.'],
                ]);
            }

            if ($onHandBefore < $quantity) {
                throw ValidationException::withMessages([
                    'quantity' => ['Insufficient sellable stock for this operation.'],
                ]);
            }

            $onHandAfter = $onHandBefore - $quantity;
            $reservedAfter = $reservedBefore - $quantity;

            $locked->forceFill([
                'on_hand' => $onHandAfter,
                'reserved' => $reservedAfter,
                'is_active' => true,
            ])->save();

            $storeId = $locked->inventoryLocation?->store_id
                ?? InventoryLocation::query()->whereKey($locked->inventory_location_id)->value('store_id');

            $meta = array_merge($metadata ?? [], [
                'reservation_convert' => true,
                'reserved_before' => $reservedBefore,
                'reserved_after' => $reservedAfter,
                'reserved_delta' => -1 * $quantity,
            ]);
            if ($idempotencyKey !== null) {
                $meta['idempotency_key'] = $idempotencyKey;
            }

            $movement = InventoryStockMovement::query()->create([
                'variant_inventory_id' => $locked->id,
                'product_variant_id' => $locked->product_variant_id,
                'inventory_location_id' => $locked->inventory_location_id,
                'store_id' => $storeId,
                'movement_type' => $movementType,
                'quantity_before' => $onHandBefore,
                'quantity_change' => -1 * $quantity,
                'quantity_after' => $onHandAfter,
                'damaged_after' => (int) $locked->damaged,
                'inspection_after' => (int) $locked->inspection,
                'reason' => $reason,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'actor_type' => $actor ? 'admin' : 'system',
                'actor_id' => $actor?->id,
                'metadata' => $meta,
                'created_at' => now(),
            ]);

            return new InventoryMutationResult(
                applied: true,
                kind: InventoryMutationKind::Sell,
                path: PurchasabilityPath::Variant,
                source: 'variant_inventories',
                quantityBefore: $onHandBefore,
                quantityChange: -1 * $quantity,
                quantityAfter: $onHandAfter,
                inventory: $locked,
                movement: $movement,
                idempotentReplay: false,
                meta: [
                    'movement_type' => $movementType->value,
                    'reservation_convert' => true,
                    'reserved_before' => $reservedBefore,
                    'reserved_after' => $reservedAfter,
                ],
            );
        });
    }

    /**
     * @param  array<string, mixed>|null  $metadata
     */
    private function convertSimpleReservedToSale(
        Inventory $inventory,
        int $quantity,
        ?string $reason,
        ?string $idempotencyKey,
        ?array $metadata,
    ): InventoryMutationResult {
        $type = 'sale';
        $storedReason = $idempotencyKey !== null
            ? $idempotencyKey.($reason !== null && $reason !== '' ? '|'.$reason : '')
            : ($reason ?? 'reservation-convert');

        return DB::transaction(function () use (
            $inventory,
            $quantity,
            $storedReason,
            $idempotencyKey,
            $type,
            $metadata,
        ) {
            /** @var Inventory $locked */
            $locked = Inventory::query()->whereKey($inventory->id)->lockForUpdate()->firstOrFail();

            if ($idempotencyKey !== null) {
                $existing = InventoryMovement::query()
                    ->where('inventory_id', $locked->id)
                    ->where('type', $type)
                    ->where('reason', 'like', $idempotencyKey.'%')
                    ->first();

                if ($existing !== null) {
                    return new InventoryMutationResult(
                        applied: true,
                        kind: InventoryMutationKind::Sell,
                        path: PurchasabilityPath::Simple,
                        source: 'inventory',
                        quantityBefore: (int) $locked->quantity,
                        quantityChange: 0,
                        quantityAfter: (int) $locked->quantity,
                        inventory: $locked,
                        movement: $existing,
                        idempotentReplay: true,
                        meta: [
                            'idempotency_key' => $idempotencyKey,
                            'reservation_convert' => true,
                        ],
                    );
                }
            }

            $onHandBefore = (int) $locked->quantity;
            $reservedBefore = (int) $locked->reserved_quantity;

            if ($reservedBefore < $quantity) {
                throw ValidationException::withMessages([
                    'quantity' => ['Cannot convert more than reserved quantity.'],
                ]);
            }

            if ($onHandBefore < $quantity) {
                throw ValidationException::withMessages([
                    'quantity' => ['Insufficient sellable stock for this operation.'],
                ]);
            }

            $onHandAfter = $onHandBefore - $quantity;
            $reservedAfter = $reservedBefore - $quantity;

            $locked->forceFill([
                'quantity' => $onHandAfter,
                'reserved_quantity' => $reservedAfter,
            ])->save();

            $movement = $locked->movements()->create([
                'quantity' => -1 * $quantity,
                'type' => $type,
                'reason' => $storedReason,
            ]);

            return new InventoryMutationResult(
                applied: true,
                kind: InventoryMutationKind::Sell,
                path: PurchasabilityPath::Simple,
                source: 'inventory',
                quantityBefore: $onHandBefore,
                quantityChange: -1 * $quantity,
                quantityAfter: $onHandAfter,
                inventory: $locked,
                movement: $movement,
                idempotentReplay: false,
                meta: array_merge($metadata ?? [], [
                    'movement_type' => $type,
                    'reservation_convert' => true,
                    'reserved_before' => $reservedBefore,
                    'reserved_after' => $reservedAfter,
                    'idempotency_key' => $idempotencyKey,
                ]),
            );
        });
    }

    /**
     * Map InventoryMovementType-driven engine calls onto gate kinds.
     */
    public static function kindFromMovementType(InventoryMovementType $type): InventoryMutationKind
    {
        return match ($type) {
            InventoryMovementType::Receive => InventoryMutationKind::Receive,
            InventoryMovementType::Sale => InventoryMutationKind::Sell,
            InventoryMovementType::Return => InventoryMutationKind::Return,
            InventoryMovementType::Damage => InventoryMutationKind::Damage,
            InventoryMovementType::Adjustment,
            InventoryMovementType::Correction => InventoryMutationKind::Adjust,
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
