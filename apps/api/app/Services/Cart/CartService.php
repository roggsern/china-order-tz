<?php

namespace App\Services\Cart;

use App\Enums\CartStatus;
use App\Enums\ShippingMethod;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CartService
{
    /**
     * @return array{
     *     checkout_type: string,
     *     cart: Cart,
     *     subtotal: string,
     *     item_count: int,
     *     ready_for_checkout: bool
     * }
     * @param  array{
     *     product_id: string,
     *     quantity: int,
     *     variant_id?: string|null,
     *     shipping_method?: string|null
     * }  $data
     */
    public function prepareBuyNow(User $user, array $data): array
    {
        [$product, $variant, $unitPrice, $shippingMethod, $shippingPrice] = $this->resolvePurchasableProduct(
            $data['product_id'],
            $data['variant_id'] ?? null,
            $data['shipping_method'] ?? null,
        );

        $cart = DB::transaction(function () use ($user, $product, $variant, $unitPrice, $data, $shippingMethod, $shippingPrice): Cart {
            $this->clearCheckoutSessions($user);

            $cart = Cart::query()->create([
                'user_id' => $user->id,
                'session_id' => null,
                'status' => CartStatus::CheckoutSession,
            ]);

            $cart->items()->create([
                'product_id' => $product->id,
                'product_variant_id' => $variant?->id,
                'quantity' => $data['quantity'],
                'unit_price' => $unitPrice,
                'shipping_method' => $shippingMethod,
                'shipping_price' => $shippingPrice,
            ]);

            return $cart;
        });

        $cart = $this->loadCart($cart);

        return [
            'checkout_type' => 'buy_now',
            'cart' => $cart,
            'subtotal' => $this->calculateCartSubtotal($cart),
            'item_count' => $cart->items->count(),
            'ready_for_checkout' => true,
        ];
    }

    public function getCart(User $user): Cart
    {
        return $this->loadCart($this->resolveActiveCart($user));
    }

    /**
     * @param  array{
     *     product_id: string,
     *     quantity: int,
     *     variant_id?: string|null,
     *     shipping_method?: string|null
     * }  $data
     */
    public function addItem(User $user, array $data): Cart
    {
        [$product, $variant, $unitPrice, $shippingMethod, $shippingPrice] = $this->resolvePurchasableProduct(
            $data['product_id'],
            $data['variant_id'] ?? null,
            $data['shipping_method'] ?? null,
        );

        $cart = $this->resolveActiveCart($user);

        $existingItem = CartItem::query()
            ->where('cart_id', $cart->id)
            ->where('product_id', $product->id)
            ->when(
                $variant,
                fn ($query) => $query->where('product_variant_id', $variant->id),
                fn ($query) => $query->whereNull('product_variant_id'),
            )
            ->first();

        if ($existingItem) {
            $existingItem->update([
                'quantity' => $existingItem->quantity + $data['quantity'],
                'unit_price' => $unitPrice,
                'shipping_method' => $shippingMethod,
                'shipping_price' => $shippingPrice,
            ]);
        } else {
            $cart->items()->create([
                'product_id' => $product->id,
                'product_variant_id' => $variant?->id,
                'quantity' => $data['quantity'],
                'unit_price' => $unitPrice,
                'shipping_method' => $shippingMethod,
                'shipping_price' => $shippingPrice,
            ]);
        }

        return $this->loadCart($cart);
    }

    public function updateItemQuantity(User $user, CartItem $item, int $quantity): Cart
    {
        $item->load('cart');
        $this->authorizeCartItem($user, $item);

        $item->update(['quantity' => $quantity]);

        return $this->loadCart($item->cart);
    }

    public function removeItem(User $user, CartItem $item): Cart
    {
        $item->load('cart');
        $this->authorizeCartItem($user, $item);

        $cart = $item->cart;
        $item->delete();

        return $this->loadCart($cart);
    }

    public function clearCart(User $user): Cart
    {
        $cart = $this->resolveActiveCart($user);
        $cart->items()->delete();

        return $this->loadCart($cart);
    }

    public function calculateCartSubtotal(Cart $cart): string
    {
        return $cart->subtotal();
    }

    public function loadCart(Cart $cart): Cart
    {
        return $cart->load(['items.product.supplier', 'items.variant']);
    }

    public function authorizeCartItem(User $user, CartItem $item): void
    {
        if ($item->cart->user_id !== $user->id || $item->cart->status !== CartStatus::Active) {
            abort(404);
        }
    }

    public function resolveActiveCart(User $user): Cart
    {
        return Cart::query()->firstOrCreate(
            [
                'user_id' => $user->id,
                'status' => CartStatus::Active,
            ],
            [
                'session_id' => null,
            ],
        );
    }

    /**
     * @return array{0: Product, 1: ProductVariant|null, 2: string, 3: ShippingMethod|null, 4: string|null}
     */
    private function resolvePurchasableProduct(
        string $productId,
        ?string $variantId = null,
        ?string $shippingMethod = null,
    ): array {
        $product = Product::query()->with('supplier')->find($productId);

        if ($product === null) {
            $this->throwValidationError('Product not found.');
        }

        if (! $product->is_active) {
            $this->throwValidationError('Product is not available.');
        }

        $variant = null;

        if (filled($variantId)) {
            $variant = ProductVariant::query()->with('product')->find($variantId);

            if ($variant === null) {
                $this->throwValidationError('Product variant not found.');
            }

            if (! $variant->is_active) {
                $this->throwValidationError('Product variant is not available.');
            }

            if ($variant->product_id !== $product->id) {
                $this->throwValidationError('Product variant does not belong to the selected product.');
            }
        }

        $unitPrice = $variant
            ? $variant->effectivePrice()
            : (string) $product->price;

        [$resolvedShippingMethod, $resolvedShippingPrice] = $this->resolveShippingSelection(
            $product,
            $shippingMethod,
        );

        return [$product, $variant, $unitPrice, $resolvedShippingMethod, $resolvedShippingPrice];
    }

    /**
     * @return array{0: ShippingMethod|null, 1: string|null}
     */
    private function resolveShippingSelection(Product $product, ?string $shippingMethod): array
    {
        if (! $product->isFromChina()) {
            if (filled($shippingMethod)) {
                $this->throwValidationError('Shipping method is not required for this product.');
            }

            return [null, null];
        }

        if (! filled($shippingMethod)) {
            $this->throwValidationError('Shipping method is required for China products.');
        }

        $method = ShippingMethod::tryFrom($shippingMethod);

        if ($method === null) {
            $this->throwValidationError('Invalid shipping method selected.');
        }

        $shippingPrice = $product->shippingPriceForMethod($method->value);

        if ($shippingPrice === null) {
            $this->throwValidationError('Selected shipping method is not available for this product.');
        }

        return [$method, $shippingPrice];
    }

    private function clearCheckoutSessions(User $user): void
    {
        Cart::query()
            ->where('user_id', $user->id)
            ->where('status', CartStatus::CheckoutSession)
            ->each(function (Cart $cart): void {
                $cart->items()->delete();
                $cart->delete();
            });
    }

    private function throwValidationError(string $message): never
    {
        throw ValidationException::withMessages([
            'product_id' => [$message],
        ]);
    }
}
