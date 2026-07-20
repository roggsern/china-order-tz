<?php

namespace App\Enums;

enum PromotionType: string
{
    case Coupon = 'coupon';
    case Automatic = 'automatic';

    public function label(): string
    {
        return match ($this) {
            self::Coupon => 'Coupon',
            self::Automatic => 'Automatic',
        };
    }
}
