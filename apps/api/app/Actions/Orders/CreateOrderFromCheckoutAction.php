<?php

namespace App\Actions\Orders;

use App\Models\CheckoutSession;
use App\Models\Order;
use App\Models\User;
use App\Services\Orders\OrderEngine;

class CreateOrderFromCheckoutAction
{
    public function __construct(
        private readonly OrderEngine $orderEngine,
    ) {}

    public function handle(User $user, CheckoutSession $session): Order
    {
        return $this->orderEngine->createFromCheckoutSession($user, $session);
    }
}
