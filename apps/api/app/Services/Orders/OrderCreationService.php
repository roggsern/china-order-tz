<?php

namespace App\Services\Orders;

use App\Models\Order;
use App\Models\User;
use App\Services\Checkout\CheckoutOrchestrator;
use App\Services\Checkout\CheckoutShippingChoiceService;
use Illuminate\Validation\ValidationException;

/**
 * Production order creation entry — always goes through Checkout Orchestrator + Order Engine.
 * Legacy direct cart→order writes are retired; this class remains as a compatibility façade
 * for POST /orders/confirm so callers still hit the single pipeline.
 */
class OrderCreationService
{
    public function __construct(
        private readonly CheckoutOrchestrator $checkoutOrchestrator,
        private readonly CheckoutShippingChoiceService $shippingChoice,
        private readonly OrderEngine $orderEngine,
    ) {}

    /**
     * @param  array{
     *     shipping_choice: string,
     *     shipping_method?: string|null,
     *     agent_name?: string|null,
     *     agent_contact?: string|null
     * }  $shippingInput
     */
    public function confirm(User $user, array $shippingInput): Order
    {
        $user->unsetRelation('deliveryAddress');
        $user->load('deliveryAddress');

        if ($user->deliveryAddress === null) {
            throw ValidationException::withMessages([
                'delivery_address' => ['Delivery address is required before checkout.'],
            ]);
        }

        $session = $this->checkoutOrchestrator->start($user);
        $session = $this->shippingChoice->apply($user, $session, $shippingInput);

        return $this->orderEngine->createFromCheckoutSession($user, $session);
    }
}
