<?php

namespace App\Services\Cart;

use App\Enums\CartStatus;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\User;
use App\Services\Commerce\CommerceChannelResolver;
use Illuminate\Support\Facades\DB;

class CartService
{
    public function __construct(
        private readonly ResolveCartPurchasable $resolveCartPurchasable,
        private readonly CommerceChannelResolver $commerceChannelResolver,
    ) {}

    /**
     * @param  array{
     *     product_id?: string|null,
     *     product_variant_id?: string|null,
     *     variant_id?: string|null,
     *     configuration_id?: string|null,
     *     quantity: int,
     *     currency?: string|null,
     *     shipping_method?: string|null
     * }  $data
     * @return array{
     *     checkout_type: string,
     *     cart: Cart,
     *     subtotal: string,
     *     item_count: int,
     *     ready_for_checkout: bool
     * }
     */
    public function prepareBuyNow(User $user, array $data): array
    {
        $resolved = $this->resolveCartPurchasable->handle(
            $data['product_id'] ?? null,
            $data['product_variant_id'] ?? $data['variant_id'] ?? $data['configuration_id'] ?? null,
            $data['quantity'],
            $data['currency'] ?? 'TZS',
            $data['shipping_method'] ?? null,
        );

        $cart = DB::transaction(function () use ($user, $resolved, $data): Cart {
            $this->clearCheckoutSessions($user);

            $cart = Cart::query()->create([
                'user_id' => $user->id,
                'session_id' => null,
                'status' => CartStatus::CheckoutSession,
                'currency' => $resolved['currency'],
            ]);

            $cart->items()->create([
                'product_id' => $resolved['product']->id,
                'product_variant_id' => $resolved['variant']->id,
                'quantity' => $data['quantity'],
                'unit_price' => $resolved['unit_price'],
                'price_snapshot' => $resolved['unit_price'],
                'currency' => $resolved['currency'],
                'shipping_method' => $resolved['shipping_method'],
                'shipping_price' => $resolved['shipping_price'],
            ]);

            return $cart;
        });

        $cart = $this->loadCart($cart);

        return [
            'checkout_type' => 'buy_now',
            'cart' => $cart,
            'subtotal' => $cart->subtotal(),
            'item_count' => $cart->itemCount(),
            'ready_for_checkout' => true,
        ];
    }

    public function getCart(User $user): Cart
    {
        return $this->loadCart($this->resolveActiveCart($user));
    }

    /**
     * @param  array{
     *     product_id?: string|null,
     *     product_variant_id?: string|null,
     *     variant_id?: string|null,
     *     configuration_id?: string|null,
     *     quantity: int,
     *     currency?: string|null,
     *     shipping_method?: string|null
     * }  $data
     */
    public function addItem(User $user, array $data): Cart
    {
        $resolved = $this->resolveCartPurchasable->handle(
            $data['product_id'] ?? null,
            $data['product_variant_id'] ?? $data['variant_id'] ?? $data['configuration_id'] ?? null,
            $data['quantity'],
            $data['currency'] ?? 'TZS',
            $data['shipping_method'] ?? null,
        );

        $cart = $this->resolveActiveCart($user, $resolved['currency']);
        $cart = $this->loadCart($cart);

        // Reject mixed CHINA_IMPORT + TZ_LOCAL carts before mutating.
        $this->commerceChannelResolver->assertCartSingleChannel($cart, $resolved['product']);

        $existingItem = CartItem::withTrashed()
            ->where('cart_id', $cart->id)
            ->where('product_variant_id', $resolved['variant']->id)
            ->first();

        if ($existingItem !== null) {
            if ($existingItem->trashed()) {
                $existingItem->restore();
            }

            $existingItem->update([
                'product_id' => $resolved['product']->id,
                'quantity' => $existingItem->quantity + $data['quantity'],
                'unit_price' => $resolved['unit_price'],
                'price_snapshot' => $resolved['unit_price'],
                'currency' => $resolved['currency'],
                'shipping_method' => $resolved['shipping_method'],
                'shipping_price' => $resolved['shipping_price'],
            ]);

            // Re-validate merged quantity against inventory.
            $this->resolveCartPurchasable->handle(
                $resolved['product']->id,
                $resolved['variant']->id,
                (int) $existingItem->fresh()->quantity,
                $resolved['currency'],
                $data['shipping_method'] ?? null,
            );
        } else {
            $cart->items()->create([
                'product_id' => $resolved['product']->id,
                'product_variant_id' => $resolved['variant']->id,
                'quantity' => $data['quantity'],
                'unit_price' => $resolved['unit_price'],
                'price_snapshot' => $resolved['unit_price'],
                'currency' => $resolved['currency'],
                'shipping_method' => $resolved['shipping_method'],
                'shipping_price' => $resolved['shipping_price'],
            ]);
        }

        return $this->loadCart($cart);
    }

    public function updateItemQuantity(User $user, CartItem $item, int $quantity): Cart
    {
        $item->load(['cart', 'variant']);
        $this->authorizeCartItem($user, $item);

        if ($item->product_variant_id === null) {
            abort(422, 'Cart item is missing a product variant.');
        }

        $resolved = $this->resolveCartPurchasable->handle(
            $item->product_id,
            $item->product_variant_id,
            $quantity,
            $item->currency ?? $item->cart->currency ?? 'TZS',
            null,
        );

        $item->update([
            'quantity' => $quantity,
            'unit_price' => $resolved['unit_price'],
            'price_snapshot' => $resolved['unit_price'],
            'currency' => $resolved['currency'],
        ]);

        return $this->loadCart($item->cart);
    }

    public function removeItem(User $user, CartItem $item): Cart
    {
        $item->load('cart');
        $this->authorizeCartItem($user, $item);

        $cart = $item->cart;
        $item->forceDelete();

        return $this->loadCart($cart);
    }

    public function clearCart(User $user): Cart
    {
        $cart = $this->resolveActiveCart($user);
        $cart->clear();

        return $this->loadCart($cart);
    }

    public function finalizeAfterOrder(User $user): void
    {
        Cart::query()
            ->where('user_id', $user->id)
            ->where('status', CartStatus::Active)
            ->each(function (Cart $cart): void {
                $cart->clear();
            });

        $this->clearCheckoutSessions($user);
    }

    public function calculateCartSubtotal(Cart $cart): string
    {
        return $cart->subtotal();
    }

    public function loadCart(Cart $cart): Cart
    {
        return $cart->load([
            'items.product.supplier',
            'items.product.brand',
            'items.product.category',
            'items.product.images',
            'items.variant.attributeValues.attribute',
            'items.variant.catalogAttributeValues.option',
            'items.variant.prices',
            'items.variant.inventories',
        ]);
    }

    public function authorizeCartItem(User $user, CartItem $item): void
    {
        if ($item->cart->user_id !== $user->id || $item->cart->status !== CartStatus::Active) {
            abort(404);
        }
    }

    public function resolveActiveCart(User $user, string $currency = 'TZS'): Cart
    {
        $cart = Cart::query()->firstOrCreate(
            [
                'user_id' => $user->id,
                'status' => CartStatus::Active,
            ],
            [
                'session_id' => null,
                'currency' => strtoupper($currency),
            ],
        );

        if ($cart->currency === null || $cart->currency === '') {
            $cart->update(['currency' => strtoupper($currency)]);
        }

        return $cart;
    }

    private function clearCheckoutSessions(User $user): void
    {
        Cart::query()
            ->where('user_id', $user->id)
            ->where('status', CartStatus::CheckoutSession)
            ->each(function (Cart $cart): void {
                $cart->items()->forceDelete();
                $cart->forceDelete();
            });
    }
}
