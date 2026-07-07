<?php

namespace App\Actions\Cart;

use App\Models\Cart;
use App\Models\User;
use App\Services\Cart\CartService;

class ClearCartAction
{
    public function __construct(
        private readonly CartService $cartService,
    ) {}

    public function handle(User $user): Cart
    {
        return $this->cartService->clearCart($user);
    }
}
