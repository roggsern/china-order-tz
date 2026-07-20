<?php

namespace App\Enums;

enum DeliveryMarket: string
{
    case China = 'china';
    case Tanzania = 'tanzania';

    public function label(): string
    {
        return match ($this) {
            self::China => 'Buy From China',
            self::Tanzania => 'Buy From Tanzania',
        };
    }
}
