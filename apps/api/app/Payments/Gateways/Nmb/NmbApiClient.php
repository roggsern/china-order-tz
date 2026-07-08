<?php

namespace App\Payments\Gateways\Nmb;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Payments\DTOs\InitiatePaymentResult;
use Illuminate\Support\Str;

class NmbApiClient
{
    public function createSession(Payment $payment): InitiatePaymentResult
    {
        if ($this->shouldUseMockSession()) {
            return $this->mockSession($payment);
        }

        // Real sandbox integration will be plugged in here with minimal changes.
        return $this->mockSession($payment);
    }

    private function shouldUseMockSession(): bool
    {
        if (! config('payments.nmb.enabled')) {
            return true;
        }

        return ! filled(config('payments.nmb.client_id'))
            || ! filled(config('payments.nmb.client_secret'))
            || ! filled(config('payments.nmb.base_url'));
    }

    private function mockSession(Payment $payment): InitiatePaymentResult
    {
        $gatewayReference = (string) Str::uuid();
        $checkoutUrl = rtrim((string) config('payments.nmb.mock_checkout_url'), '/')
            .'/'.($payment->reference ?? $payment->id);

        $gatewayResponse = [
            'mode' => 'mock',
            'reference' => $payment->reference,
            'amount' => (string) $payment->amount,
            'currency' => $payment->currency,
            'gateway_reference' => $gatewayReference,
        ];

        return new InitiatePaymentResult(
            success: true,
            status: PaymentStatus::Initiated->value,
            message: 'Payment session created.',
            checkoutRequestId: $gatewayReference,
            gatewayReference: $gatewayReference,
            checkoutUrl: $checkoutUrl,
            gatewayResponse: $gatewayResponse,
        );
    }
}
