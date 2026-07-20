<?php

namespace App\Actions\Checkout;

use App\Models\CheckoutSession;
use App\Models\User;
use App\Services\Checkout\CheckoutOrchestrator;

class RefreshCheckoutSessionAction
{
    public function __construct(
        private readonly CheckoutOrchestrator $orchestrator,
    ) {}

    public function handle(User $user, CheckoutSession $session): CheckoutSession
    {
        return $this->orchestrator->refresh($user, $session);
    }
}
