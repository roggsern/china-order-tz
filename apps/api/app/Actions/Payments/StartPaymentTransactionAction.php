<?php

namespace App\Actions\Payments;

use App\Models\Order;
use App\Models\PaymentTransaction;
use App\Models\User;
use App\Services\Payments\Orchestration\PaymentOrchestrator;

class StartPaymentTransactionAction
{
    public function __construct(
        private readonly PaymentOrchestrator $orchestrator,
    ) {}

    public function handle(User $user, Order $order, ?string $provider = null): PaymentTransaction
    {
        return $this->orchestrator->start($user, $order, $provider);
    }
}
