<?php

namespace App\Services\Checkout;

use App\Enums\CartStatus;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\DeliveryAddress;
use App\Models\User;
use App\Services\Cart\ResolveCartPurchasable;
use Illuminate\Validation\ValidationException;

/**
 * Legacy prepare / preview surface.
 * RC1-C1 — address + CommercePricingResolver line pricing parity with checkout session.
 * Channel mix and promotions remain session/confirm concerns; prepare still surfaces shipping summaries.
 */
class CheckoutService
{
    public function __construct(
        private readonly ResolveCartPurchasable $resolveCartPurchasable,
    ) {}

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
        $deliveryAddress = $this->resolveDeliveryAddress($user);
        $cart = $this->resolveCheckoutCart($user);
        $this->validateCartHasItems($cart);
        $cart = $this->loadCart($user, $cart);

        $subtotal = $this->refreshLinePricing($cart);
        $chinaShippingTotal = $this->calculateChinaShippingTotal($cart);
        $shippingSummary = $this->buildShippingSummary($cart, $chinaShippingTotal);
        $grandTotal = bcadd($subtotal, $chinaShippingTotal, 2);

        return [
            'customer' => $user,
            'delivery_address' => $deliveryAddress,
            'cart' => $cart->fresh([
                'items.product.commerceChannel',
                'items.variant',
            ]) ?? $cart,
            'subtotal' => $subtotal,
            'shipping_summary' => $shippingSummary,
            'grand_total' => $grandTotal,
            'ready_for_confirmation' => true,
        ];
    }

    /**
     * Re-price every line through ResolveCartPurchasable (CommercePricingResolver + stock).
     */
    private function refreshLinePricing(Cart $cart): string
    {
        $currency = strtoupper((string) ($cart->currency ?: 'TZS'));
        $subtotal = '0.00';

        foreach ($cart->items as $item) {
            if ($item->quantity < 1) {
                throw ValidationException::withMessages([
                    'quantity' => ['Quantity must be at least 1.'],
                ]);
            }

            $resolved = $this->resolveCartPurchasable->handle(
                $item->product_id,
                $item->product_variant_id,
                (int) $item->quantity,
                $currency,
                $item->shipping_method?->value,
            );

            $unit = (string) $resolved['unit_price'];
            $subtotal = bcadd($subtotal, bcmul($unit, (string) $item->quantity, 2), 2);

            $item->forceFill([
                'price_snapshot' => $resolved['unit_price'],
                'unit_price' => $resolved['unit_price'],
                'currency' => $resolved['currency'],
            ])->save();
        }

        return $subtotal;
    }

    private function loadCart(User $user, Cart $cart): Cart
    {
        $cart->load([
            'items.product.commerceChannel',
            'items.product.inventory',
            'items.product.shippingOptions',
            'items.variant.inventories',
            'items.variant.prices',
        ]);

        if ($cart->user_id !== $user->id) {
            abort(404);
        }

        return $cart;
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
        $user->unsetRelation('deliveryAddress');
        $user->load('deliveryAddress');
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

    private function calculateChinaShippingTotal(Cart $cart): string
    {
        $total = '0.00';

        foreach ($cart->items as $item) {
            if (! $item->product?->requiresChinaShipping()) {
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
            fn (CartItem $item) => (bool) $item->product?->requiresChinaShipping(),
        );

        $hasDarItems = $cart->items->contains(
            fn (CartItem $item) => $item->product !== null && ! $item->product->requiresChinaShipping(),
        );

        $summary = [];

        if ($hasChinaItems) {
            $summary['china_shipping_total'] = $chinaShippingTotal;
            $summary['china_items'] = $cart->items
                ->filter(fn (CartItem $item) => (bool) $item->product?->requiresChinaShipping())
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
