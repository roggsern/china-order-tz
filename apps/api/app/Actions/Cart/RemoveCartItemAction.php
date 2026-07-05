<?php

namespace App\Actions\Cart;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\User;

class RemoveCartItemAction
{
    use ResolvesUserCart;

    public function handle(User $user, CartItem $item): Cart
    {
        $item->load('cart');
        $this->authorizeCartItem($user, $item);

        $cart = $item->cart;
        $item->delete();

        return $this->loadCart($cart);
    }
}
