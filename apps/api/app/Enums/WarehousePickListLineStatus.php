<?php

namespace App\Enums;

enum WarehousePickListLineStatus: string
{
    case Pending = 'pending';
    case Partial = 'partial';
    case Picked = 'picked';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Partial => 'Partial',
            self::Picked => 'Picked',
        };
    }
}
