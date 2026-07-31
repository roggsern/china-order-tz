<?php

namespace App\Enums;

enum GrowthInsightSeverity: string
{
    case High = 'HIGH';
    case Medium = 'MEDIUM';
    case Low = 'LOW';

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
