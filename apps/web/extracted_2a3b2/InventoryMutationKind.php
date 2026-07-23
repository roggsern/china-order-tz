<?php

namespace App\Enums;

/**
 * Canonical inventory mutation kinds for InventoryMutationGate (ADR 055).
 * Maps onto existing ledger movement types without schema change.
 */
enum InventoryMutationKind: string
{
    case Receive = 'receive';
    case Adjust = 'adjust';
    case Damage = 'damage';
    case Return = 'return';
    case Sell = 'sell';

    public function toVariantMovementType(?string $adjustSubtype = null): InventoryMovementType
    {
        return match ($this) {
            self::Receive => InventoryMovementType::Receive,
            self::Sell => InventoryMovementType::Sale,
            self::Return => InventoryMovementType::Return,
            self::Damage => InventoryMovementType::Damage,
            self::Adjust => match ($adjustSubtype) {
                'correction', 'found' => InventoryMovementType::Correction,
                default => InventoryMovementType::Adjustment,
            },
        };
    }

    /**
     * Legacy inventory_movements.type string for Simple path.
     */
    public function toSimpleMovementType(?string $adjustSubtype = null): string
    {
        return match ($this) {
            self::Receive => 'restock',
            self::Sell => 'sale',
            self::Return => 'restock',
            self::Damage => 'adjustment',
            self::Adjust => $adjustSubtype === 'correction' ? 'adjustment' : 'adjustment',
        };
    }
}
