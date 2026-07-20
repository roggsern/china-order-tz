<?php

namespace App\Services\Fulfillment;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Services\Commerce\CommerceChannelResolver;

/**
 * Resolves whether an order (or line) needs China procurement vs local warehouse.
 * Prefers commerce channel (order snapshot / product channel) over legacy heuristics.
 */
class FulfillmentStrategyResolver
{
    public function __construct(
        private readonly CommerceChannelResolver $commerceChannelResolver,
    ) {}

    public function orderRequiresChina(Order $order): bool
    {
        if ($order->commerce_channel_snapshot || $order->commerce_channel_id) {
            return $this->commerceChannelResolver->strategyForOrder($order)->usesImportFulfillment();
        }

        $order->loadMissing(['items.product.commerceChannel', 'items.product.supplier', 'items.shippingMethodRecord']);

        foreach ($order->items as $item) {
            if ($this->itemRequiresChina($item)) {
                return true;
            }
        }

        return false;
    }

    public function itemRequiresChina(OrderItem $item): bool
    {
        /** @var Product|null $product */
        $product = $item->product;

        if ($product !== null) {
            if ($product->commerce_channel_id || $product->relationLoaded('commerceChannel')) {
                return $this->commerceChannelResolver->strategyForProduct($product)->usesImportFulfillment();
            }

            $source = strtolower((string) ($product->fulfillment_source ?? ''));
            if ($source === 'imported_from_china') {
                return true;
            }
            if ($source === 'buy_from_tz') {
                return false;
            }
            if ($product->isFromChina() || $product->requiresChinaShipping()) {
                return true;
            }
        }

        $shippingSource = strtolower((string) ($item->shippingMethodRecord?->fulfillment_source ?? ''));
        if ($shippingSource === 'imported_from_china') {
            return true;
        }

        $method = strtolower((string) ($item->shipping_method ?? ''));

        return in_array($method, ['air_freight', 'sea_freight'], true);
    }
}
