<?php

namespace App\Actions\Cart;

use App\Http\Requests\Cart\BuyNowRequest;
use App\Models\User;
use App\Services\Cart\CartService;

class BuyNowAction
{
    public function __construct(
        private readonly CartService $cartService,
    ) {}

    /**
     * @return array{
     *     checkout_type: string,
     *     cart: \App\Models\Cart,
     *     subtotal: string,
     *     item_count: int,
     *     ready_for_checkout: bool
     * }
     */
    public function handle(BuyNowRequest $request, User $user): array
    {
        return $this->cartService->prepareBuyNow($user, $request->validated());
    }
}
