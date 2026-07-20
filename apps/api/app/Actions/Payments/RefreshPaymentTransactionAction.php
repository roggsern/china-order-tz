<?php

namespace App\Actions\Payments;

use App\Models\PaymentTransaction;
use App\Models\User;
use App\Services\Payments\Orchestration\PaymentOrchestrator;

class RefreshPaymentTransactionAction
{
    public function __construct(
        private readonly PaymentOrchestrator $orchestrator,
    ) {}

    public function handle(User $user, PaymentTransaction $transaction): PaymentTransaction
    {
        return $this->orchestrator->refresh($user, $transaction);
    }
}
