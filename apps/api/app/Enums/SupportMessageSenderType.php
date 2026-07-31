<?php

namespace App\Enums;

enum SupportMessageSenderType: string
{
    case Customer = 'customer';
    case Admin = 'admin';
    case System = 'system';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
