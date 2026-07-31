<?php

namespace App\Support\Admin;

enum PermissionRiskTier: string
{
    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';

    public function label(): string
    {
        return match ($this) {
            self::Low => 'LOW',
            self::Medium => 'MEDIUM',
            self::High => 'HIGH',
        };
    }
}
