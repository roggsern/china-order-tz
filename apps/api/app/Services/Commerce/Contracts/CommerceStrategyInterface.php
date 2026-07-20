<?php

namespace App\Services\Commerce\Contracts;

use App\Enums\CommerceChannelCode;
use App\Enums\DeliveryType;
use App\Models\CommerceChannel;

/**
 * Channel-specific business rules. Commerce logic stays shared; strategies differ.
 */
interface CommerceStrategyInterface
{
    public function code(): CommerceChannelCode;

    public function channel(): ?CommerceChannel;

    /** @return list<DeliveryType> */
    public function allowedDeliveryTypes(): array;

    /** @return list<string> air|sea — empty when not applicable */
    public function allowedShippingMethods(): array;

    public function customerSourceLabel(): string;

    public function adminLabel(): string;

    public function usesImportFulfillment(): bool;

    public function usesLocalFulfillment(): bool;

    public function allowsDeliveryType(DeliveryType $type): bool;
}
