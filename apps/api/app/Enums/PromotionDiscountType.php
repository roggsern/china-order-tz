<?php

namespace App\Enums;

enum PromotionDiscountType: string
{
    case Percentage = 'percentage';
    case FixedAmount = 'fixed_amount';
    case FreeShipping = 'free_shipping';

    public function label(): string
    {
        return match ($this) {
            self::Percentage => 'Percentage',
            self::FixedAmount => 'Fixed Amount',
            self::FreeShipping => 'Free Shipping',
        };
    }
}
