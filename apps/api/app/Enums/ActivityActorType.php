<?php

namespace App\Enums;

enum ActivityActorType: string
{
    case Admin = 'admin';
    case System = 'system';
    case Customer = 'customer';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Admin',
            self::System => 'System',
            self::Customer => 'Customer',
        };
    }
}
