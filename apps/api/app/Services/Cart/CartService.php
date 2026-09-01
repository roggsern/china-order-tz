<?php

namespace App\Services\Cart;

use App\Enums\CartStatus;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\User;
use App\Services\Commerce\CommerceChannelResolver;
use App\Services\Purchasing\AssertPurchaseQuantity;
use Illuminate\Support\Facades\DB;

class CartService
{
    public function __construct(
        private readonly ResolveCartPurchasable $resolveCartPurchasable,
        private readonly CommerceChannelResolver $commerceChannelResolver,
        private readonly AssertPurchaseQuantity $assertPurchaseQuantity,
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

        $this->assertPurchaseQuantity->assertLegal(
            $resolved['product'],
            (int) $data['quantity'],
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
                'product_variant_id' => $resolved['variant']?->id,
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
        // Read-only. Volume prices are persisted on cart mutations so GET does
        // not write-on-read. Checkout/order independently reprice anyway.
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
        $incomingQuantity = (int) $data['quantity'];
        $resolved = $this->resolveCartPurchasable->handle(
            $data['product_id'] ?? null,
            $data['product_variant_id'] ?? $data['variant_id'] ?? $data['configuration_id'] ?? null,
            $incomingQuantity,
            $data['currency'] ?? 'TZS',
            $data['shipping_method'] ?? null,
        );

        $cart = $this->resolveActiveCart($user, $resolved['currency']);
        $cart = $this->loadCart($cart);

        // Reject mixed CHINA_IMPORT + TZ_LOCAL carts before mutating.
        $this->commerceChannelResolver->assertCartSingleChannel($cart, $resolved['product']);

        $variantId = $resolved['variant']?->id;
        $productId = $resolved['product']->id;

        return DB::transaction(function () use ($cart, $resolved, $incomingQuantity, $variantId, $productId): Cart {
            $existingQuery = CartItem::withTrashed()
                ->where('cart_id', $cart->id);

            if ($variantId !== null) {
                $existingQuery->where('product_variant_id', $variantId);
            } else {
                $existingQuery
                    ->where('product_id', $productId)
                    ->whereNull('product_variant_id');
            }

            $existingItem = $existingQuery->first();

            if ($existingItem !== null) {
                if ($existingItem->trashed()) {
                    $existingItem->restore();
                }

                $existingItem->update([
                    'product_id' => $productId,
                    'quantity' => $existingItem->quantity + $incomingQuantity,
                    'unit_price' => $resolved['unit_price'],
                    'price_snapshot' => $resolved['unit_price'],
                    'currency' => $resolved['currency'],
                    'shipping_method' => $resolved['shipping_method'],
                    'shipping_price' => $resolved['shipping_price'],
                ]);
            } else {
                $cart->items()->create([
                    'product_id' => $productId,
                    'product_variant_id' => $variantId,
                    'quantity' => $incomingQuantity,
                    'unit_price' => $resolved['unit_price'],
                    'price_snapshot' => $resolved['unit_price'],
                    'currency' => $resolved['currency'],
                    'shipping_method' => $resolved['shipping_method'],
                    'shipping_price' => $resolved['shipping_price'],
                ]);
            }

            $this->repriceProductLines($cart, $productId);

            return $this->loadCart($cart, includeVariantPresentation: false);
        });
    }

    public function updateItemQuantity(User $user, CartItem $item, int $quantity): Cart
    {
        $item->load(['cart', 'variant']);
        $this->authorizeCartItem($user, $item);

        $cart = $item->cart;
        $productId = (string) $item->product_id;

        return DB::transaction(function () use ($item, $cart, $quantity, $productId): Cart {
            $item->update([
                'quantity' => $quantity,
            ]);

            $this->repriceProductLines($cart, $productId);

            return $this->loadCart($cart, includeVariantPresentation: false);
        });
    }

    public function removeItem(User $user, CartItem $item): Cart
    {
        $item->load('cart');
        $this->authorizeCartItem($user, $item);

        $cart = $item->cart;
        $productId = (string) $item->product_id;

        return DB::transaction(function () use ($item, $cart, $productId): Cart {
            $item->forceDelete();
            $this->repriceProductLines($cart, $productId);

            return $this->loadCart($cart, includeVariantPresentation: false);
        });
    }

    public function clearCart(User $user): Cart
    {
        $cart = $this->resolveActiveCart($user);
        $cart->clear();

        return $this->loadCart($cart, includeVariantPresentation: false);
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

    /**
     * @param  bool  $includeVariantPresentation  Full variant attrs for cart UI GET;
     *                                            false for mutation responses used by checkout sync.
     *                                            Slim mutations still eager-load product/variant media
     *                                            so line images stay selected-variant-aware.
     */
    public function loadCart(Cart $cart, bool $includeVariantPresentation = true): Cart
    {
        if (! $includeVariantPresentation) {
            return $cart->load([
                'items.product.commerceChannel',
                'items.product.brand',
                'items.product.category',
                'items.product.productType',
                'items.product.variants',
                'items.product.media' => fn ($query) => $query->images()->active()->ordered(),
                'items.product.shippingOptions',
                'items.variant.product',
                'items.variant.media' => fn ($query) => $query->images()->active()->ordered(),
                'items.variant.prices',
                'items.variant.inventories',
                'items.variant.inventory',
                'items.variant.chinaCommercialStock',
            ]);
        }

        return $cart->load([
            'items.product.commerceChannel',
            'items.product.brand',
            'items.product.category',
            'items.product.productType',
            'items.product.variants',
            'items.product.images',
            'items.product.media' => fn ($query) => $query->images()->active()->ordered(),
            'items.product.shippingOptions',
            'items.variant.product',
            'items.variant.media' => fn ($query) => $query->images()->active()->ordered(),
            'items.variant.attributeValues.attribute',
            'items.variant.catalogAttributeValues.attribute',
            'items.variant.catalogAttributeValues.option',
            'items.variant.prices',
            'items.variant.inventories',
        ]);
    }

    public function authorizeCartItem(User $user, CartItem $item): void
    {
        if ($item->cart->user_id !== $user->id || $item->cart->status !== CartStatus::Active) {
            abort(404, 'Cart item not found.');
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

    /**
     * Re-price every line of a product using combined same-product quantity
     * for volume-tier eligibility. Stock and shipping stay per line/SKU.
     */
    private function repriceProductLines(Cart $cart, string $productId): void
    {
        $items = CartItem::query()
            ->where('cart_id', $cart->id)
            ->where('product_id', $productId)
            ->get();

        if ($items->isEmpty()) {
            return;
        }

        $aggregate = CartProductPricingQuantity::forProduct($items, $productId);
        $currency = strtoupper((string) ($cart->currency ?: 'TZS'));

        foreach ($items as $item) {
            $resolved = $this->resolveCartPurchasable->handle(
                $item->product_id,
                $item->product_variant_id,
                (int) $item->quantity,
                $item->currency ?? $currency,
                null,
                $aggregate,
            );

            $item->forceFill([
                'unit_price' => $resolved['unit_price'],
                'price_snapshot' => $resolved['unit_price'],
                'currency' => $resolved['currency'],
            ])->save();
        }
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
