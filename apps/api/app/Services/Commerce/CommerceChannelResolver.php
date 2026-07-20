<?php

namespace App\Services\Commerce;

use App\Enums\CommerceChannelCode;
use App\Models\Cart;
use App\Models\CommerceChannel;
use App\Models\Order;
use App\Models\Product;
use App\Services\Commerce\Contracts\CommerceStrategyInterface;
use App\Services\Commerce\Strategies\ChinaCommerceStrategy;
use App\Services\Commerce\Strategies\TanzaniaCommerceStrategy;
use Illuminate\Validation\ValidationException;

/**
 * Resolves commerce channel + strategy for products, carts, and orders.
 * Does not duplicate checkout/fulfillment systems.
 */
class CommerceChannelResolver
{
    public function channelByCode(CommerceChannelCode|string $code): CommerceChannel
    {
        $value = $code instanceof CommerceChannelCode ? $code->value : $code;

        return CommerceChannel::query()
            ->where('code', $value)
            ->where('is_active', true)
            ->firstOrFail();
    }

    public function resolveProductChannel(Product $product): CommerceChannel
    {
        if ($product->relationLoaded('commerceChannel') && $product->commerceChannel !== null) {
            return $product->commerceChannel;
        }

        if ($product->commerce_channel_id) {
            $channel = CommerceChannel::query()->find($product->commerce_channel_id);
            if ($channel !== null) {
                return $channel;
            }
        }

        // Legacy fallback via fulfillment_source.
        $code = CommerceChannelCode::fromFulfillmentSource($product->fulfillment_source ?? null);

        return $this->channelByCode($code);
    }

    public function strategyFor(CommerceChannel|CommerceChannelCode|string $channel): CommerceStrategyInterface
    {
        $model = null;
        $code = null;

        if ($channel instanceof CommerceChannel) {
            $model = $channel;
            $code = CommerceChannelCode::tryFrom($channel->code) ?? CommerceChannelCode::ChinaImport;
        } elseif ($channel instanceof CommerceChannelCode) {
            $code = $channel;
            $model = CommerceChannel::query()->where('code', $code->value)->first();
        } else {
            $code = CommerceChannelCode::tryFrom($channel) ?? CommerceChannelCode::ChinaImport;
            $model = CommerceChannel::query()->where('code', $code->value)->first();
        }

        return match ($code) {
            CommerceChannelCode::TzLocal => new TanzaniaCommerceStrategy($model),
            default => new ChinaCommerceStrategy($model),
        };
    }

    public function strategyForProduct(Product $product): CommerceStrategyInterface
    {
        return $this->strategyFor($this->resolveProductChannel($product));
    }

    /**
     * Ensures cart items share a single commerce channel.
     *
     * @throws ValidationException
     */
    public function assertCartSingleChannel(Cart $cart, ?Product $incoming = null): CommerceChannel
    {
        $cart->loadMissing(['items.product.commerceChannel']);

        $codes = [];
        foreach ($cart->items as $item) {
            if ($item->product === null) {
                continue;
            }
            $codes[] = $this->resolveProductChannel($item->product)->code;
        }

        if ($incoming !== null) {
            $codes[] = $this->resolveProductChannel($incoming)->code;
        }

        $codes = array_values(array_unique($codes));

        if (count($codes) > 1) {
            throw ValidationException::withMessages([
                'cart' => [
                    'A cart cannot mix Buy From China and Buy From Tanzania products. Please checkout separately.',
                ],
            ]);
        }

        if ($codes === []) {
            throw ValidationException::withMessages([
                'cart' => ['Unable to resolve commerce channel for this cart.'],
            ]);
        }

        return $this->channelByCode($codes[0]);
    }

    public function resolveOrderChannel(Order $order): CommerceChannel
    {
        $snapshot = $order->commerce_channel_snapshot;
        if (is_array($snapshot) && filled($snapshot['code'] ?? null)) {
            $existing = CommerceChannel::query()->where('code', $snapshot['code'])->first();
            if ($existing !== null) {
                return $existing;
            }

            // Historical snapshot even if channel row changed — synthetic model.
            $synthetic = new CommerceChannel([
                'name' => $snapshot['name'] ?? $snapshot['code'],
                'code' => $snapshot['code'],
                'description' => $snapshot['description'] ?? null,
                'is_active' => true,
            ]);
            $synthetic->id = $snapshot['id'] ?? null;

            return $synthetic;
        }

        if ($order->commerce_channel_id) {
            $channel = CommerceChannel::query()->find($order->commerce_channel_id);
            if ($channel !== null) {
                return $channel;
            }
        }

        $order->loadMissing(['items.product.commerceChannel']);
        foreach ($order->items as $item) {
            if ($item->product !== null) {
                return $this->resolveProductChannel($item->product);
            }
        }

        return $this->channelByCode(CommerceChannelCode::ChinaImport);
    }

    public function strategyForOrder(Order $order): CommerceStrategyInterface
    {
        return $this->strategyFor($this->resolveOrderChannel($order));
    }

    /**
     * @return array{id: string, code: string, name: string, description: string|null, customer_label: string}
     */
    public function snapshot(CommerceChannel $channel): array
    {
        $code = CommerceChannelCode::tryFrom($channel->code) ?? CommerceChannelCode::ChinaImport;

        return [
            'id' => $channel->id,
            'code' => $channel->code,
            'name' => $channel->name,
            'description' => $channel->description,
            'customer_label' => $code->customerSourceLabel(),
        ];
    }
}
