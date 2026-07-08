<?php

namespace App\Services\Orders;

use App\Enums\OrderStatus;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\User;
use App\Services\Cart\CartService;
use App\Services\Checkout\CheckoutService;
use Illuminate\Support\Facades\DB;

class OrderCreationService
{
    public function __construct(
        private readonly CheckoutService $checkoutService,
        private readonly OrderNumberGenerator $orderNumberGenerator,
        private readonly CartService $cartService,
    ) {}

    public function confirm(User $user): Order
    {
        $prepared = $this->checkoutService->prepare($user);

        /** @var Cart $cart */
        $cart = $prepared['cart'];

        return DB::transaction(function () use ($user, $prepared, $cart): Order {
            $order = Order::query()->create([
                'user_id' => $user->id,
                'order_number' => $this->orderNumberGenerator->generate(),
                'status' => OrderStatus::Pending,
                'subtotal' => $prepared['subtotal'],
                'discount_amount' => '0.00',
                'tax_amount' => '0.00',
                'shipping_amount' => $prepared['shipping_summary']['china_shipping_total'] ?? '0.00',
                'total' => $prepared['grand_total'],
                'currency' => 'TZS',
                'placed_at' => now(),
            ]);

            foreach ($cart->items as $item) {
                $order->items()->create($this->buildOrderItemPayload($item));
            }

            $this->cartService->finalizeAfterOrder($user);

            return $order->load(['items.product.supplier', 'items.variant']);
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function buildOrderItemPayload(CartItem $item): array
    {
        $item->loadMissing(['product.supplier', 'variant']);

        $payload = [
            'product_id' => $item->product_id,
            'product_variant_id' => $item->product_variant_id,
            'product_name' => $item->product->name,
            'variant_name' => $item->variant?->name,
            'sku' => $item->variant?->sku ?? $item->product->sku,
            'quantity' => $item->quantity,
            'unit_price' => $item->unit_price,
            'total_price' => $item->subtotal(),
        ];

        if ($item->product->isFromChina()) {
            $payload['shipping_method'] = $item->shipping_method?->value;
            $payload['shipping_price'] = $item->shipping_price;
            $payload['shipping_subtotal'] = $item->shippingSubtotal();
        } else {
            $payload['delivery_status'] = 'To Be Negotiated';
        }

        return $payload;
    }
}
