<?php

namespace App\Services\Inventory\DTOs;

use App\Enums\InventoryMutationKind;
use App\Models\Admin;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\VariantInventory;

/**
 * Mutation request context for InventoryMutationGate (ADR 055 / Phase 2A-3B-2).
 *
 * Reservation / commitment / channel / warehouse allocation fields are reserved
 * extension points — unused in this sprint.
 */
final class InventoryMutationContext
{
    public function __construct(
        public readonly InventoryMutationKind $kind,
        public readonly int $quantityChange,
        public readonly ?Product $product = null,
        public readonly ?ProductVariant $variant = null,
        public readonly Inventory|VariantInventory|null $inventory = null,
        public readonly ?Admin $actor = null,
        public readonly ?string $reason = null,
        public readonly ?string $referenceType = null,
        public readonly ?string $referenceId = null,
        /** @var array<string, mixed>|null */
        public readonly ?array $metadata = null,
        public readonly int $damagedDelta = 0,
        public readonly int $inspectionDelta = 0,
        /**
         * Adjust subtype for Variant ledger: adjustment|correction|found.
         */
        public readonly ?string $adjustSubtype = null,
        public readonly ?string $warehouseCode = null,
        public readonly ?string $inventoryLocationId = null,
        public readonly ?string $storeId = null,
        public readonly ?string $channel = null,
        /** Reserved — not applied in 2A-3B-2. */
        public readonly bool $isReservation = false,
        /** Reserved — not applied in 2A-3B-2. */
        public readonly bool $isCommitment = false,
        /**
         * When set, skip creating a duplicate movement if one already exists
         * with metadata.idempotency_key matching this value.
         */
        public readonly ?string $idempotencyKey = null,
    ) {}
}
