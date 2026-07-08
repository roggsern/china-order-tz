<?php

namespace App\Actions\CustomerOrders;

use App\Models\Order;
use App\Models\User;
use App\Services\Orders\OrderCreationService;

class ConfirmCheckoutAction
{
    public function __construct(
        private readonly OrderCreationService $orderCreationService,
    ) {}

    public function handle(User $user): Order
    {
        return $this->orderCreationService->confirm($user);
    }
}
