<?php

namespace App\Services\Checkout;

use App\Enums\CartStatus;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\DeliveryAddress;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class CheckoutService
{
    /**
     * @return array{
     *     customer: User,
     *     delivery_address: DeliveryAddress,
     *     cart: Cart,
     *     subtotal: string,
     *     shipping_summary: array<string, mixed>,
     *     grand_total: string,
     *     ready_for_confirmation: bool
     * }
     */
    public function prepare(User $user): array
    {
        $cart = $this->resolveCheckoutCart($user);
        $this->validateCartHasItems($cart);
        $deliveryAddress = $this->resolveDeliveryAddress($user);

        $cart->load(['items.product.supplier', 'items.variant']);

        $this->validateChinaItemsHaveShipping($cart);

        $subtotal = $this->calculateItemsSubtotal($cart);
        $chinaShippingTotal = $this->calculateChinaShippingTotal($cart);
        $grandTotal = bcadd($subtotal, $chinaShippingTotal, 2);

        return [
            'customer' => $user,
            'delivery_address' => $deliveryAddress,
            'cart' => $cart,
            'subtotal' => $subtotal,
            'shipping_summary' => $this->buildShippingSummary($cart, $chinaShippingTotal),
            'grand_total' => $grandTotal,
            'ready_for_confirmation' => true,
        ];
    }

    private function resolveCheckoutCart(User $user): Cart
    {
        $checkoutSessionCart = Cart::query()
            ->where('user_id', $user->id)
            ->where('status', CartStatus::CheckoutSession)
            ->withCount('items')
            ->first();

        if ($checkoutSessionCart !== null && $checkoutSessionCart->items_count > 0) {
            return $checkoutSessionCart;
        }

        $activeCart = Cart::query()
            ->where('user_id', $user->id)
            ->where('status', CartStatus::Active)
            ->first();

        if ($activeCart === null) {
            $this->throwValidationError('cart', 'Active cart not found.');
        }

        return $activeCart;
    }

    private function resolveDeliveryAddress(User $user): DeliveryAddress
    {
        $address = $user->deliveryAddress;

        if ($address === null) {
            $this->throwValidationError('delivery_address', 'Delivery address is required before checkout.');
        }

        return $address;
    }

    private function validateCartHasItems(Cart $cart): void
    {
        $cart->loadCount('items');

        if ($cart->items_count === 0) {
            $this->throwValidationError('cart', 'Cart is empty.');
        }
    }

    private function validateChinaItemsHaveShipping(Cart $cart): void
    {
        foreach ($cart->items as $item) {
            if (! $item->product->isFromChina()) {
                continue;
            }

            if ($item->shipping_method === null || $item->shipping_price === null) {
                $this->throwValidationError(
                    'cart',
                    "Shipping method is required for {$item->product->name}.",
                );
            }
        }
    }

    private function calculateItemsSubtotal(Cart $cart): string
    {
        $total = '0.00';

        foreach ($cart->items as $item) {
            $total = bcadd($total, $item->subtotal(), 2);
        }

        return $total;
    }

    private function calculateChinaShippingTotal(Cart $cart): string
    {
        $total = '0.00';

        foreach ($cart->items as $item) {
            if (! $item->product->isFromChina()) {
                continue;
            }

            $total = bcadd($total, $item->shippingSubtotal() ?? '0.00', 2);
        }

        return $total;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildShippingSummary(Cart $cart, string $chinaShippingTotal): array
    {
        $hasChinaItems = $cart->items->contains(
            fn (CartItem $item) => $item->product->isFromChina(),
        );

        $hasDarItems = $cart->items->contains(
            fn (CartItem $item) => ! $item->product->isFromChina(),
        );

        $summary = [];

        if ($hasChinaItems) {
            $summary['china_shipping_total'] = $chinaShippingTotal;
            $summary['china_items'] = $cart->items
                ->filter(fn (CartItem $item) => $item->product->isFromChina())
                ->map(fn (CartItem $item) => [
                    'product_id' => $item->product_id,
                    'shipping_method' => $item->shipping_method?->value,
                    'shipping_price' => $item->shipping_price,
                    'quantity' => $item->quantity,
                    'shipping_subtotal' => $item->shippingSubtotal(),
                ])
                ->values()
                ->all();
        }

        if ($hasDarItems) {
            $summary['dar_delivery_status'] = 'To Be Negotiated';
        }

        return $summary;
    }

    private function throwValidationError(string $field, string $message): never
    {
        throw ValidationException::withMessages([
            $field => [$message],
        ]);
    }
}
