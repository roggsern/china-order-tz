<?php

namespace App\Actions\Checkout;

use App\Models\CheckoutSession;
use App\Models\User;
use App\Services\Checkout\CheckoutShippingChoiceService;

class ApplyCheckoutShippingChoiceAction
{
    public function __construct(
        private readonly CheckoutShippingChoiceService $shippingChoice,
    ) {}

    /**
     * @param  array{
     *     shipping_choice: string,
     *     shipping_method?: string|null,
     *     agent_name?: string|null,
     *     agent_contact?: string|null
     * }  $input
     */
    public function handle(User $user, CheckoutSession $session, array $input): CheckoutSession
    {
        return $this->shippingChoice->apply($user, $session, $input);
    }
}
