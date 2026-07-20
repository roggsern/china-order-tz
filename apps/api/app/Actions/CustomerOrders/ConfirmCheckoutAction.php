<?php

namespace App\Actions\CustomerOrders;

use App\Models\Order;
use App\Models\User;
use App\Services\Orders\OrderCreationService;

/**
 * Compatibility entry for POST /orders/confirm.
 * Delegates to Checkout Orchestrator + shipping choice + Order Engine.
 */
class ConfirmCheckoutAction
{
    public function __construct(
        private readonly OrderCreationService $orderCreationService,
    ) {}

    /**
     * @param  array{
     *     shipping_choice: string,
     *     shipping_method?: string|null,
     *     agent_name?: string|null,
     *     agent_contact?: string|null
     * }  $shippingInput
     */
    public function handle(User $user, array $shippingInput): Order
    {
        return $this->orderCreationService->confirm($user, $shippingInput);
    }
}
