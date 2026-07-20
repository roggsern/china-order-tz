<?php

namespace App\Enums;

enum ReturnItemResolution: string
{
    case Refund = 'refund';
    case Replacement = 'replacement';
    case Reject = 'reject';

    public function label(): string
    {
        return match ($this) {
            self::Refund => 'Refund',
            self::Replacement => 'Replacement',
            self::Reject => 'Reject',
        };
    }
}
