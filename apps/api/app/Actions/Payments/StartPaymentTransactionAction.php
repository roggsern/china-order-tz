<?php

namespace App\Actions\Payments;

use App\Models\Order;
use App\Models\PaymentTransaction;
use App\Models\User;
use App\Services\Payments\Orchestration\PaymentOrchestrator;
use App\Services\Payments\PaymentConfigurationResolver;

class StartPaymentTransactionAction
{
    public function __construct(
        private readonly PaymentOrchestrator $orchestrator,
        private readonly PaymentConfigurationResolver $paymentConfiguration,
    ) {}

    public function handle(User $user, Order $order, ?string $provider = null): PaymentTransaction
    {
        $resolvedProvider = $this->paymentConfiguration->resolveStartProvider($provider);

        return $this->orchestrator->start($user, $order, $resolvedProvider);
    }
}
