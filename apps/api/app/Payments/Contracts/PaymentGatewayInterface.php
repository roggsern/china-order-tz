<?php

namespace App\Payments\Contracts;

use App\Models\Payment;
use App\Payments\Results\PaymentResult;

interface PaymentGatewayInterface
{
    public function process(Payment $payment): PaymentResult;

    public function refund(Payment $payment): PaymentResult;
}
