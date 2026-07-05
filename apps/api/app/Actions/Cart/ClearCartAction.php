<?php

namespace App\Actions\Cart;

use App\Models\Cart;
use App\Models\User;

class ClearCartAction
{
    use ResolvesUserCart;

    public function handle(User $user): Cart
    {
        $cart = $this->resolveUserCart($user);
        $cart->items()->delete();

        return $this->loadCart($cart);
    }
}
