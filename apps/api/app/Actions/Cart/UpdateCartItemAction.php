<?php

namespace App\Actions\Cart;

use App\Http\Requests\Cart\UpdateCartItemRequest;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\User;

class UpdateCartItemAction
{
    use ResolvesUserCart;

    public function handle(UpdateCartItemRequest $request, User $user, CartItem $item): Cart
    {
        $item->load('cart');
        $this->authorizeCartItem($user, $item);

        $item->update([
            'quantity' => $request->validated('quantity'),
        ]);

        return $this->loadCart($item->cart);
    }
}
