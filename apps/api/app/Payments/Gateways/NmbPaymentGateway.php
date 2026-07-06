<?php

namespace App\Payments\Gateways;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Payments\Contracts\PaymentGatewayInterface;
use App\Payments\Results\PaymentResult;

class NmbPaymentGateway implements PaymentGatewayInterface
{
    public function process(Payment $payment): PaymentResult
    {
        return new PaymentResult(
            success: false,
            status: PaymentStatus::Pending->value,
            message: 'NMB payment gateway is not implemented yet.',
        );
    }

    public function refund(Payment $payment): PaymentResult
    {
        return new PaymentResult(
            success: false,
            status: PaymentStatus::Pending->value,
            message: 'NMB payment gateway refund is not implemented yet.',
        );
    }
}
