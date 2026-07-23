<?php

namespace App\Enums;

enum InventoryDisposition: string
{
    case Sellable = 'sellable';
    case Damaged = 'damaged';
    /** Legacy POS hold label — not a finalized online completion disposition. */
    case Inspection = 'inspection';
    /** Explicit online hold — must not complete until remapped to sellable/damaged/no_restock. */
    case InspectionHold = 'inspection_hold';
    case NoRestock = 'no_restock';

    public function label(): string
    {
        return match ($this) {
            self::Sellable => 'Sellable',
            self::Damaged => 'Damaged',
            self::Inspection, self::InspectionHold => 'Inspection Required',
            self::NoRestock => 'No Restock',
        };
    }

    public function restocksSellable(): bool
    {
        return $this === self::Sellable;
    }

    public function recordsDamagedIntake(): bool
    {
        return $this === self::Damaged;
    }

    /**
     * Dispositions allowed when transitioning an online return to Completed.
     */
    public function isFinalizedForCompletion(): bool
    {
        return in_array($this, [self::Sellable, self::Damaged, self::NoRestock], true);
    }
}
