<?php

namespace App\Actions\Payments;

use App\Models\PaymentTransaction;
use App\Services\Payments\Orchestration\PaymentOrchestrator;

class ReconcileNmbBrowserReturnAction
{
    public function __construct(
        private readonly PaymentOrchestrator $orchestrator,
    ) {}

    public function handle(
        string $paymentTransactionId,
        string $merchantReference,
        string $successIndicator,
        string $resultIndicator,
        ?string $orderId = null,
    ): PaymentTransaction {
        return $this->orchestrator->reconcileNmbBrowserReturn(
            $paymentTransactionId,
            $merchantReference,
            $successIndicator,
            $resultIndicator,
            $orderId,
        );
    }
}
