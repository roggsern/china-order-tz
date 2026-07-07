<?php

namespace App\Actions\Cart;

use App\Http\Requests\Cart\StoreCartItemRequest;
use App\Models\Cart;
use App\Models\User;
use App\Services\Cart\CartService;

class AddToCartAction
{
    public function __construct(
        private readonly CartService $cartService,
    ) {}

    public function handle(StoreCartItemRequest $request, User $user): Cart
    {
        return $this->cartService->addItem($user, $request->validated());
    }
}
