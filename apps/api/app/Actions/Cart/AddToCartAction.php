<?php

namespace App\Actions\Cart;

use App\Http\Requests\Cart\StoreCartItemRequest;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;

class AddToCartAction
{
    use ResolvesUserCart;

    public function handle(StoreCartItemRequest $request, User $user): Cart
    {
        $validated = $request->validated();
        $cart = $this->resolveUserCart($user);

        $product = Product::query()->findOrFail($validated['product_id']);

        $variant = filled($validated['variant_id'] ?? null)
            ? ProductVariant::query()->with('product')->findOrFail($validated['variant_id'])
            : null;

        $unitPrice = $variant
            ? $variant->effectivePrice()
            : (string) $product->price;

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
                'quantity' => $existingItem->quantity + $validated['quantity'],
                'unit_price' => $unitPrice,
            ]);
        } else {
            $cart->items()->create([
                'product_id' => $product->id,
                'product_variant_id' => $variant?->id,
                'quantity' => $validated['quantity'],
                'unit_price' => $unitPrice,
            ]);
        }

        return $this->loadCart($cart);
    }
}
