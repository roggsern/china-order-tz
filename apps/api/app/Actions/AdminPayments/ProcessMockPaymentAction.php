<?php

namespace App\Actions\AdminPayments;

use App\Actions\Payments\ProcessPaymentAction;
use App\Models\Order;
use App\Models\Payment;

class ProcessMockPaymentAction
{
    public function __construct(
        private readonly ProcessPaymentAction $processPaymentAction,
    ) {}

    /**
     * @return array{payment?: Payment, order?: Order, failed: bool}
     */
    public function handle(Payment $payment, string $result): array
    {
        $payment->metadata = array_merge($payment->metadata ?? [], [
            'mock_result' => $result,
        ]);

        return $this->processPaymentAction->handle($payment);
    }
}
