<?php

namespace App\Actions\Payments;

use App\Enums\PaymentMethod;
use App\Models\Payment;
use App\Models\User;
use App\Payments\Contracts\AsyncPaymentGatewayInterface;
use App\Payments\DTOs\InitiatePaymentResult;
use App\Payments\Services\PaymentService;
use Illuminate\Validation\ValidationException;

class InitiatePaymentAction
{
    public function __construct(
        private readonly PaymentService $paymentService,
    ) {}

    public function handle(Payment $payment, User $user): InitiatePaymentResult
    {
        $this->authorizePayment($payment, $user);

        if (! in_array($payment->method, [PaymentMethod::Nmb, PaymentMethod::BankTransfer], true)) {
            $this->throwValidationError('This payment method does not support initiation.');
        }

        $gateway = $this->paymentService->gatewayFor($payment);

        if (! $gateway instanceof AsyncPaymentGatewayInterface) {
            $this->throwValidationError('The selected gateway does not support payment initiation.');
        }

        return $gateway->initiate($payment);
    }

    private function authorizePayment(Payment $payment, User $user): void
    {
        if ($payment->user_id !== $user->id) {
            abort(404);
        }
    }

    private function throwValidationError(string $message): never
    {
        throw ValidationException::withMessages([
            'payment' => [$message],
        ]);
    }
}
