<?php

namespace App\Enums;

enum ProductCondition: string
{
    case BrandNew = 'BRAND_NEW';
    case OpenBox = 'OPEN_BOX';
    case Refurbished = 'REFURBISHED';
    case Used = 'USED';

    public function label(): string
    {
        return match ($this) {
            self::BrandNew => 'Brand New',
            self::OpenBox => 'Open Box',
            self::Refurbished => 'Refurbished',
            self::Used => 'Used',
        };
    }

    public function storefrontBadge(): string
    {
        return match ($this) {
            self::BrandNew => 'Brand New',
            self::OpenBox => 'Open Box',
            self::Refurbished => 'Refurbished',
            self::Used => 'Used',
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
