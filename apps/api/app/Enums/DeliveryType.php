<?php

namespace App\Enums;

enum DeliveryType: string
{
    case CompanyShipping = 'company_shipping';
    case CustomerAgent = 'customer_agent';
    case SelfPickup = 'self_pickup';
    case NegotiatedDelivery = 'negotiated_delivery';

    public function label(): string
    {
        return match ($this) {
            self::CompanyShipping => 'Company Shipping',
            self::CustomerAgent => 'Customer Agent',
            self::SelfPickup => 'Self Pickup',
            self::NegotiatedDelivery => 'Negotiated Delivery',
        };
    }

    public function isChinaType(): bool
    {
        return in_array($this, [self::CompanyShipping, self::CustomerAgent], true);
    }

    public function isTanzaniaType(): bool
    {
        return in_array($this, [self::SelfPickup, self::NegotiatedDelivery], true);
    }
}
