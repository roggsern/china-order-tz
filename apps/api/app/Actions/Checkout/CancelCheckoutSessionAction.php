<?php

namespace App\Actions\Checkout;

use App\Models\CheckoutSession;
use App\Models\User;
use App\Services\Checkout\CheckoutOrchestrator;

class CancelCheckoutSessionAction
{
    public function __construct(
        private readonly CheckoutOrchestrator $orchestrator,
    ) {}

    public function handle(User $user, CheckoutSession $session): void
    {
        $this->orchestrator->cancel($user, $session);
    }
}
