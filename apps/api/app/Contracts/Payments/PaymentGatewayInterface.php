<?php

namespace App\Contracts\Payments;

use App\DataTransferObjects\Payments\PaymentResult;
use App\Models\Payment;

interface PaymentGatewayInterface
{
    public function process(Payment $payment): PaymentResult;

    public function refund(Payment $payment): PaymentResult;
}
