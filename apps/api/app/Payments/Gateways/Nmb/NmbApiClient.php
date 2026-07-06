<?php

namespace App\Payments\Gateways\Nmb;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Payments\DTOs\InitiatePaymentResult;
use Illuminate\Support\Str;

class NmbApiClient
{
    public function initiate(Payment $payment): InitiatePaymentResult
    {
        if (! config('payments.nmb.enabled')) {
            return new InitiatePaymentResult(
                success: false,
                status: PaymentStatus::Pending->value,
                message: 'NMB gateway is disabled.',
            );
        }

        return new InitiatePaymentResult(
            success: true,
            status: PaymentStatus::Processing->value,
            message: 'Payment initiation stub.',
            checkoutRequestId: (string) Str::uuid(),
        );
    }
}
