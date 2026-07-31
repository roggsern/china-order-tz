<?php

namespace App\Actions\Checkout;

use App\Models\CheckoutSession;
use App\Models\User;
use App\Services\Checkout\CheckoutOrchestrator;

class StartCheckoutSessionAction
{
    public function __construct(
        private readonly CheckoutOrchestrator $orchestrator,
    ) {}

    /**
     * @param  array{visitor_uuid?: string|null, session_id?: string|null}  $attribution
     */
    public function handle(User $user, array $attribution = []): CheckoutSession
    {
        return $this->orchestrator->start($user, $attribution);
    }
}
