<?php

namespace App\Enums;

enum FulfillmentStrategy: string
{
    case Local = 'local';
    case China = 'china';

    public function label(): string
    {
        return match ($this) {
            self::Local => 'Local Warehouse',
            self::China => 'China Procurement',
        };
    }
}
