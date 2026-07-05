<?php

namespace App\Actions\AdminPayments;

use App\Actions\AdminOrders\ShowOrderAction;
use App\Contracts\Payments\PaymentGatewayInterface;
use App\Models\Order;
use App\Models\Payment;

class ProcessMockPaymentAction
{
    public function __construct(
        private readonly PaymentGatewayInterface $gateway,
        private readonly ShowOrderAction $showOrderAction,
    ) {}

    /**
     * @return array{payment?: Payment, order?: Order, failed: bool}
     */
    public function handle(Payment $payment, string $result): array
    {
        $payment->metadata = array_merge($payment->metadata ?? [], [
            'mock_result' => $result,
        ]);

        $paymentResult = $this->gateway->process($payment);

        if (! $paymentResult->success) {
            return ['failed' => true];
        }

        $payment = $payment->fresh()->load(['order']);

        return [
            'failed' => false,
            'payment' => $payment,
            'order' => $this->showOrderAction->handle($payment->order),
        ];
    }
}
