<?php

namespace App\Enums;

enum VariantPriceType: string
{
    case Retail = 'retail';
    case Wholesale = 'wholesale';
    case Dealer = 'dealer';
    case Vip = 'vip';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
