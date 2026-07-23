<?php

namespace App\Services\Inventory\DTOs;

/**
 * Resolution context for StockResolver (ADR 055 / Phase 2A-3B-1).
 *
 * Reservation / location / channel / region are reserved extension points —
 * they are not applied in this foundation sprint.
 */
final class StockResolutionContext
{
    public function __construct(
        /**
         * Commerce default warehouse for Variant path (production: MAIN).
         */
        public readonly string $warehouseCode = 'MAIN',
        public readonly ?string $inventoryLocationId = null,
        public readonly ?string $storeId = null,
        public readonly ?string $channel = null,
        public readonly ?string $region = null,
        /**
         * Reserved: future soft-hold awareness for Available calculation.
         * Foundation sprint reads existing reserved columns only.
         */
        public readonly bool $includeReservations = true,
    ) {}

    public function warehouseCode(): string
    {
        return strtoupper($this->warehouseCode);
    }
}
