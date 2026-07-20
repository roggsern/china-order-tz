<?php

namespace App\Enums;

enum ProductVisibility: string
{
    case Public = 'public';
    case Private = 'private';
    case Hidden = 'hidden';

    public function isStorefrontVisible(): bool
    {
        return $this === self::Public;
    }

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
