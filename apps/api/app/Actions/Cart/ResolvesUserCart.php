<?php

namespace App\Actions\Cart;

use App\Enums\CartStatus;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\User;

trait ResolvesUserCart
{
    protected function resolveUserCart(User $user): Cart
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

    protected function loadCart(Cart $cart): Cart
    {
        return $cart->load(['items.product', 'items.variant']);
    }

    protected function authorizeCartItem(User $user, CartItem $item): void
    {
        if ($item->cart->user_id !== $user->id) {
            abort(404);
        }
    }
}
