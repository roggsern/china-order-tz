<?php

namespace App\Actions\Payments;

use App\Enums\PaymentMethod;
use App\Models\Order;
use App\Models\Payment;
use App\Payments\Contracts\AsyncPaymentGatewayInterface;
use App\Payments\Services\PaymentService;
use Illuminate\Validation\ValidationException;

class SimulateNmbCallbackAction
{
    public function __construct(
        private readonly PaymentService $paymentService,
        private readonly CompletePaymentAction $completePaymentAction,
    ) {}

    /**
     * @return array{payment?: Payment, order?: Order, failed: bool}
     */
    public function handle(Payment $payment, string $result): array
    {
        if (! in_array($payment->method, [PaymentMethod::Nmb, PaymentMethod::BankTransfer], true)) {
            $this->throwValidationError('Payment is not an NMB payment.');
        }

        $gateway = $this->paymentService->gatewayFor($payment);

        if (! $gateway instanceof AsyncPaymentGatewayInterface) {
            $this->throwValidationError('The selected gateway does not support callbacks.');
        }

        $paymentResult = $gateway->handleCallback($payment, ['result' => $result]);

        if (! $paymentResult->success) {
            return ['failed' => true];
        }

        $payment = $payment->fresh()->load(['order']);
        $order = $this->completePaymentAction->handle($payment);

        return [
            'failed' => false,
            'payment' => $payment,
            'order' => $order,
        ];
    }

    private function throwValidationError(string $message): never
    {
        throw ValidationException::withMessages([
            'payment' => [$message],
        ]);
    }
}
