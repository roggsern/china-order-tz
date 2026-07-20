<?php

namespace App\Enums;

enum PriceTierType: string
{
    case FixedUnit = 'fixed_unit';
    case PercentOff = 'percent_off';

    public static function tryFromMixed(mixed $value): self
    {
        if ($value instanceof self) {
            return $value;
        }

        if (! is_string($value)) {
            return self::FixedUnit;
        }

        $normalized = strtolower(trim(str_replace('-', '_', $value)));

        return match ($normalized) {
            'percent_off', 'percent', 'percentage' => self::PercentOff,
            default => self::FixedUnit,
        };
    }
}
