<?php

namespace App\Enums;

enum CustomerLifecycleStatus: string
{
    case Active = 'active';
    case Dormant = 'dormant';
    case Blocked = 'blocked';
    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Dormant => 'Dormant',
            self::Blocked => 'Blocked',
            self::Closed => 'Closed',
        };
    }
}
