<?php

namespace App\Actions\Cart;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\User;
use App\Services\Cart\CartService;

class RemoveCartItemAction
{
    public function __construct(
        private readonly CartService $cartService,
    ) {}

    public function handle(User $user, CartItem $item): Cart
    {
        return $this->cartService->removeItem($user, $item);
    }
}
