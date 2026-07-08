<?php

namespace App\Actions\Payments;

use App\Models\Payment;
use App\Models\User;
use App\Services\Payments\PaymentSessionService;

class InitiatePaymentAction
{
    public function __construct(
        private readonly PaymentSessionService $paymentSessionService,
    ) {}

    public function handle(Payment $payment, User $user): Payment
    {
        return $this->paymentSessionService->initiate($payment, $user);
    }
}
