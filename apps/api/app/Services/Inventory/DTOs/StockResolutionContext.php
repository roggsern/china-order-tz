<?php

namespace App\Services\Inventory\DTOs;

/**
 * Resolution context for StockResolver (ADR 055 / Phase 2A-3B-1).
 *
 * Reservation / location / channel / region are reserved extension points —
 * they are not applied in this foundation sprint.
 *
 * ADMIN-11.3: commerceSellableOnly keeps checkout on MAIN; China/IN_TRANSIT are never sellable.
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
        /**
         * When true, refuse non-sellable warehouses (CHINA / IN_TRANSIT).
         * Default true so customer commerce never reads China stock.
         */
        public readonly bool $commerceSellableOnly = true,
    ) {}

    public function warehouseCode(): string
    {
        return strtoupper($this->warehouseCode);
    }

    /**
     * Reporting / pipeline reads for China or in-transit warehouses.
     */
    public static function forWarehouse(string $warehouseCode): self
    {
        return new self(
            warehouseCode: strtoupper($warehouseCode),
            commerceSellableOnly: false,
        );
    }
}
