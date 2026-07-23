<?php

namespace App\Services\Inventory\DTOs;

use App\Enums\PurchasabilityPath;
use App\Models\Inventory;
use App\Models\VariantInventory;

/**
 * Catalog Stock read result (ADR 055).
 * Not a mutation or commitment record — callers must not treat this as write authority.
 */
final class StockResolutionResult
{
    /**
     * @param  array<string, mixed>  $meta
     */
    public function __construct(
        public readonly bool $resolved,
        public readonly string $source,
        public readonly string $inventoryType,
        public readonly int $quantityOnHand,
        public readonly int $quantityReserved,
        public readonly int $quantityAvailable,
        public readonly ?string $location,
        public readonly PurchasabilityPath $path,
        public readonly Inventory|VariantInventory|null $inventory = null,
        public readonly array $meta = [],
    ) {}

    /**
     * @param  array<string, mixed>  $meta
     */
    public static function unresolved(
        PurchasabilityPath $path,
        string $source = 'unresolved',
        string $inventoryType = 'none',
        array $meta = [],
    ): self {
        return new self(
            resolved: false,
            source: $source,
            inventoryType: $inventoryType,
            quantityOnHand: 0,
            quantityReserved: 0,
            quantityAvailable: 0,
            location: null,
            path: $path,
            inventory: null,
            meta: $meta,
        );
    }

    /**
     * Policy presence: Catalog Stock row exists for the sell path (qty may be zero).
     */
    public function hasInventoryPolicy(): bool
    {
        return $this->resolved && $this->inventory !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'resolved' => $this->resolved,
            'source' => $this->source,
            'inventory_type' => $this->inventoryType,
            'quantity_on_hand' => $this->quantityOnHand,
            'quantity_reserved' => $this->quantityReserved,
            'quantity_available' => $this->quantityAvailable,
            'location' => $this->location,
            'path' => $this->path->value,
            'inventory_id' => $this->inventory?->id,
            'meta' => $this->meta,
        ];
    }
}
