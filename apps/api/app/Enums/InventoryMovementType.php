<?php

namespace App\Enums;

enum InventoryMovementType: string
{
    case Receive = 'receive';
    case Sale = 'sale';
    case Return = 'return';
    case Adjustment = 'adjustment';
    case Damage = 'damage';
    case Correction = 'correction';

    public function label(): string
    {
        return match ($this) {
            self::Receive => 'Receive',
            self::Sale => 'Sale',
            self::Return => 'Return',
            self::Adjustment => 'Adjustment',
            self::Damage => 'Damage',
            self::Correction => 'Correction',
        };
    }
}
