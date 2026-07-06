<?php

namespace App\Actions\Payments;

use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Payments\Services\PaymentService;
use Illuminate\Validation\ValidationException;

class ProcessPaymentAction
{
    public function __construct(
        private readonly PaymentService $paymentService,
        private readonly CompletePaymentAction $completePaymentAction,
    ) {}

    /**
     * @return array{payment?: Payment, order?: Order, failed: bool}
     */
    public function handle(Payment $payment): array
    {
        if ($payment->status !== PaymentStatus::Pending) {
            $this->throwValidationError('Only pending payments can be processed.');
        }

        $gateway = $this->paymentService->gatewayFor($payment);
        $paymentResult = $gateway->process($payment);

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
