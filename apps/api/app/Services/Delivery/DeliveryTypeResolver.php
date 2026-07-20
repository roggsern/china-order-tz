<?php

namespace App\Services\Delivery;

use App\Enums\DeliveryMarket;
use App\Enums\DeliveryType;
use App\Models\Order;
use App\Services\Commerce\CommerceChannelResolver;

/**
 * Resolves Buy From China vs Buy From Tanzania for delivery option eligibility.
 * Prefers order commerce channel snapshot (immutable) over product mutations.
 */
class DeliveryTypeResolver
{
    public function __construct(
        private readonly CommerceChannelResolver $commerceChannelResolver,
    ) {}

    public function resolveMarket(Order $order): DeliveryMarket
    {
        $strategy = $this->commerceChannelResolver->strategyForOrder($order);

        return $strategy->usesImportFulfillment()
            ? DeliveryMarket::China
            : DeliveryMarket::Tanzania;
    }

    /**
     * @return list<DeliveryType>
     */
    public function allowedTypes(Order $order): array
    {
        return $this->commerceChannelResolver->strategyForOrder($order)->allowedDeliveryTypes();
    }

    public function allows(Order $order, DeliveryType $type): bool
    {
        return $this->commerceChannelResolver->strategyForOrder($order)->allowsDeliveryType($type);
    }
}
