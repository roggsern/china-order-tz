<?php

namespace App\Services\Fulfillment\Strategies;

use App\Enums\FulfillmentStrategy;
use App\Models\Fulfillment;
use App\Models\Order;
use App\Services\Fulfillment\Contracts\FulfillmentStrategyInterface;
use App\Services\Fulfillment\FulfillmentStrategyResolver;

class LocalFulfillmentStrategy implements FulfillmentStrategyInterface
{
    public function __construct(
        private readonly FulfillmentStrategyResolver $resolver,
    ) {}

    public function key(): FulfillmentStrategy
    {
        return FulfillmentStrategy::Local;
    }

    public function appliesTo(Order $order): bool
    {
        $order->loadMissing(['items.product.supplier', 'items.shippingMethodRecord']);

        if ($order->items->isEmpty()) {
            return true;
        }

        return ! $this->resolver->orderRequiresChina($order);
    }

    public function bootstrap(Fulfillment $fulfillment): void
    {
        if (! filled($fulfillment->notes)) {
            $fulfillment->forceFill([
                'notes' => 'Local warehouse fulfillment. Pick from TZ stock when ready.',
            ])->save();
        }
    }
}
