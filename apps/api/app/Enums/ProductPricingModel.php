<?php

namespace App\Enums;

enum ProductPricingModel: string
{
    case Simple = 'simple';
    case Variant = 'variant';

    public static function tryFromMixed(mixed $value): ?self
    {
        if ($value instanceof self) {
            return $value;
        }

        if (! is_string($value)) {
            return null;
        }

        return self::tryFrom(strtolower(trim($value)));
    }
}
