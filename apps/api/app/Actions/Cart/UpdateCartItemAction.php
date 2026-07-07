<?php

namespace App\Actions\Cart;

use App\Http\Requests\Cart\UpdateCartItemRequest;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\User;
use App\Services\Cart\CartService;

class UpdateCartItemAction
{
    public function __construct(
        private readonly CartService $cartService,
    ) {}

    public function handle(UpdateCartItemRequest $request, User $user, CartItem $item): Cart
    {
        return $this->cartService->updateItemQuantity(
            $user,
            $item,
            $request->validated('quantity'),
        );
    }
}
