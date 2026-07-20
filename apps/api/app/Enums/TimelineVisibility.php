<?php

namespace App\Enums;

/**
 * Visibility for composed timeline entries.
 * Customer never sees internal-only operational detail.
 */
enum TimelineVisibility: string
{
    case Customer = 'customer';
    case Internal = 'internal';

    public function label(): string
    {
        return match ($this) {
            self::Customer => 'Customer',
            self::Internal => 'Internal',
        };
    }
}
