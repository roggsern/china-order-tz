<?php

namespace App\Enums;

enum PromotionStatus: string
{
    case Draft = 'draft';
    case Active = 'active';
    case Inactive = 'inactive';
    case Expired = 'expired';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Active => 'Active',
            self::Inactive => 'Inactive',
            self::Expired => 'Expired',
        };
    }
}
