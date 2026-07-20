<?php

namespace App\Enums;

enum PosVarianceReason: string
{
    case CustomerChangeMistake = 'customer_change_mistake';
    case CashCountingError = 'cash_counting_error';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::CustomerChangeMistake => 'Customer change mistake',
            self::CashCountingError => 'Cash counting error',
            self::Other => 'Other',
        };
    }
}
