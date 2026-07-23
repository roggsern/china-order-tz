<?php

namespace App\Services\Inventory\DTOs;

use App\Enums\InventoryMutationKind;
use App\Enums\PurchasabilityPath;
use App\Models\Inventory;
use App\Models\InventoryMovement;
use App\Models\InventoryStockMovement;
use App\Models\VariantInventory;

/**
 * Result of a gated inventory mutation (ADR 055).
 */
final class InventoryMutationResult
{
    /**
     * @param  array<string, mixed>  $meta
     */
    public function __construct(
        public readonly bool $applied,
        public readonly InventoryMutationKind $kind,
        public readonly PurchasabilityPath $path,
        public readonly string $source,
        public readonly int $quantityBefore,
        public readonly int $quantityChange,
        public readonly int $quantityAfter,
        public readonly Inventory|VariantInventory|null $inventory = null,
        public readonly InventoryStockMovement|InventoryMovement|null $movement = null,
        public readonly bool $idempotentReplay = false,
        public readonly array $meta = [],
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'applied' => $this->applied,
            'kind' => $this->kind->value,
            'path' => $this->path->value,
            'source' => $this->source,
            'quantity_before' => $this->quantityBefore,
            'quantity_change' => $this->quantityChange,
            'quantity_after' => $this->quantityAfter,
            'inventory_id' => $this->inventory?->id,
            'movement_id' => $this->movement?->id,
            'idempotent_replay' => $this->idempotentReplay,
            'meta' => $this->meta,
        ];
    }
}
