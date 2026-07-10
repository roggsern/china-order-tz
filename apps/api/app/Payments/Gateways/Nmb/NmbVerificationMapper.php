<?php

namespace App\Payments\Gateways\Nmb;

use App\Models\Payment;

class NmbVerificationMapper
{
    /**
     * @param  array<string, mixed>  $response
     */
    public function fromResponse(array $response, Payment $payment): NmbVerificationResult
    {
        $result = isset($response['result']) ? (string) $response['result'] : null;
        $order = is_array($response['order'] ?? null) ? $response['order'] : [];
        $transaction = is_array($response['transaction'] ?? null) ? $response['transaction'] : [];

        $orderId = isset($order['id']) ? (string) $order['id'] : null;
        $amount = isset($order['amount']) ? (string) $order['amount'] : null;
        $currency = isset($order['currency']) ? (string) $order['currency'] : null;
        $transactionId = isset($transaction['id']) ? (string) $transaction['id'] : null;

        if (strtoupper($result ?? '') !== 'SUCCESS') {
            return new NmbVerificationResult(
                verified: false,
                message: (string) (
                    $response['error']['explanation']
                    ?? $response['error']['cause']
                    ?? 'NMB order verification did not succeed.'
                ),
                result: $result,
                orderId: $orderId,
                transactionId: $transactionId,
                amount: $amount,
                currency: $currency,
                rawResponse: $response,
            );
        }

        if ($orderId !== (string) $payment->reference) {
            return new NmbVerificationResult(
                verified: false,
                message: 'Verified order id does not match payment reference.',
                result: $result,
                orderId: $orderId,
                transactionId: $transactionId,
                amount: $amount,
                currency: $currency,
                rawResponse: $response,
            );
        }

        if ($amount !== null && bccomp($amount, number_format((float) $payment->amount, 2, '.', ''), 2) !== 0) {
            return new NmbVerificationResult(
                verified: false,
                message: 'Verified amount does not match payment amount.',
                result: $result,
                orderId: $orderId,
                transactionId: $transactionId,
                amount: $amount,
                currency: $currency,
                rawResponse: $response,
            );
        }

        if ($currency !== null && strtoupper($currency) !== strtoupper((string) $payment->currency)) {
            return new NmbVerificationResult(
                verified: false,
                message: 'Verified currency does not match payment currency.',
                result: $result,
                orderId: $orderId,
                transactionId: $transactionId,
                amount: $amount,
                currency: $currency,
                rawResponse: $response,
            );
        }

        return new NmbVerificationResult(
            verified: true,
            message: 'NMB transaction verified successfully.',
            result: $result,
            orderId: $orderId,
            transactionId: $transactionId,
            amount: $amount,
            currency: $currency,
            rawResponse: $response,
        );
    }
}
