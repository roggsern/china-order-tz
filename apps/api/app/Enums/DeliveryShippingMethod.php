<?php

namespace App\Enums;

enum DeliveryShippingMethod: string
{
    case Air = 'air';
    case Sea = 'sea';

    public function label(): string
    {
        return match ($this) {
            self::Air => 'Air Freight',
            self::Sea => 'Sea Freight',
        };
    }
}
