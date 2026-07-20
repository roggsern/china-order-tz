<?php

namespace App\Enums;

enum InventoryDisposition: string
{
    case Sellable = 'sellable';
    case Damaged = 'damaged';
    case Inspection = 'inspection';

    public function label(): string
    {
        return match ($this) {
            self::Sellable => 'Sellable',
            self::Damaged => 'Damaged',
            self::Inspection => 'Inspection Required',
        };
    }

    public function restocksSellable(): bool
    {
        return $this === self::Sellable;
    }
}
