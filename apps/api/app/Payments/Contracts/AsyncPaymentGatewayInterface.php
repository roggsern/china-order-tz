<?php

namespace App\Payments\Contracts;

use App\Models\Payment;
use App\Payments\DTOs\InitiatePaymentResult;
use App\Payments\Results\PaymentResult;
use App\Payments\Results\VerificationResult;

interface AsyncPaymentGatewayInterface
{
    public function initiate(Payment $payment): InitiatePaymentResult;

    public function handleCallback(Payment $payment, array $payload): PaymentResult;

    public function verify(Payment $payment): VerificationResult;
}
