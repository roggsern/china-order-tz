<?php

namespace App\Enums;

enum ProductMediaType: string
{
    case Image = 'image';
    case Video = 'video';

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
