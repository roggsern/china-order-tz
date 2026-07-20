<?php

namespace App\Services\Fulfillment\Contracts;

use App\Enums\FulfillmentStrategy;
use App\Models\Fulfillment;
use App\Models\Order;

interface FulfillmentStrategyInterface
{
    public function key(): FulfillmentStrategy;

    /**
     * Whether this strategy applies given order line signals.
     * Resolution prefers China when any item needs procurement.
     */
    public function appliesTo(Order $order): bool;

    /**
     * Optional bootstrap after the fulfillment row is created.
     * Must not create shipments, labels, or customer notifications.
     */
    public function bootstrap(Fulfillment $fulfillment): void;
}
