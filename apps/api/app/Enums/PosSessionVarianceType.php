<?php

namespace App\Enums;

enum PosSessionVarianceType: string
{
    case Balanced = 'balanced';
    case Over = 'over';
    case Short = 'short';

    public function label(): string
    {
        return match ($this) {
            self::Balanced => 'Balanced',
            self::Over => 'Over',
            self::Short => 'Short',
        };
    }

    public static function fromDifference(string $difference): self
    {
        $cmp = bccomp($difference, '0.00', 2);

        return match (true) {
            $cmp > 0 => self::Over,
            $cmp < 0 => self::Short,
            default => self::Balanced,
        };
    }
}
