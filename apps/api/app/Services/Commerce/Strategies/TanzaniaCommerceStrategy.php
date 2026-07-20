<?php

namespace App\Services\Commerce\Strategies;

use App\Enums\CommerceChannelCode;
use App\Enums\DeliveryType;
use App\Models\CommerceChannel;
use App\Services\Commerce\Contracts\CommerceStrategyInterface;

class TanzaniaCommerceStrategy implements CommerceStrategyInterface
{
    public function __construct(
        private readonly ?CommerceChannel $channel = null,
    ) {}

    public function code(): CommerceChannelCode
    {
        return CommerceChannelCode::TzLocal;
    }

    public function channel(): ?CommerceChannel
    {
        return $this->channel;
    }

    public function allowedDeliveryTypes(): array
    {
        return [
            DeliveryType::SelfPickup,
            DeliveryType::NegotiatedDelivery,
        ];
    }

    public function allowedShippingMethods(): array
    {
        return [];
    }

    public function customerSourceLabel(): string
    {
        return $this->code()->customerSourceLabel();
    }

    public function adminLabel(): string
    {
        return $this->code()->label();
    }

    public function usesImportFulfillment(): bool
    {
        return false;
    }

    public function usesLocalFulfillment(): bool
    {
        return true;
    }

    public function allowsDeliveryType(DeliveryType $type): bool
    {
        return in_array($type, $this->allowedDeliveryTypes(), true);
    }
}
