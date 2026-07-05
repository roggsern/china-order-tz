<?php

namespace App\Actions\Cart;

use App\Models\Cart;
use App\Models\User;

class GetCartAction
{
    use ResolvesUserCart;

    public function handle(User $user): Cart
    {
        return $this->loadCart($this->resolveUserCart($user));
    }
}
