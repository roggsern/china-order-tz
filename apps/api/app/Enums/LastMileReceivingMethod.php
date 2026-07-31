<?php

namespace App\Enums;

enum LastMileReceivingMethod: string
{
    case SelfPickup = 'self_pickup';
    case NegotiatedDelivery = 'negotiated_delivery';

    public function label(): string
    {
        return match ($this) {
            self::SelfPickup => 'Self Pickup',
            self::NegotiatedDelivery => 'Delivery Arrangement',
        };
    }
}
