<?php

namespace App\Payments\Gateways\Nmb;

use App\Models\Payment;

class NmbVerificationMapper
{
    public function __construct(
        private readonly NmbPaymentOutcomeEvaluator $outcomeEvaluator,
    ) {}

    /**
     * @param  array<string, mixed>  $response
     */
    public function fromResponse(array $response, Payment $payment): NmbVerificationResult
    {
        $evaluated = $this->outcomeEvaluator->evaluate(
            $response,
            expectedOrderId: (string) $payment->reference,
            expectedAmount: number_format((float) $payment->amount, 2, '.', ''),
            expectedCurrency: (string) $payment->currency,
        );

        $order = is_array($response['order'] ?? null) ? $response['order'] : [];
        $transaction = is_array($response['transaction'] ?? null) ? $response['transaction'] : [];

        $orderId = isset($order['id']) ? (string) $order['id'] : null;
        $amount = isset($order['amount']) ? (string) $order['amount'] : null;
        $currency = isset($order['currency']) ? (string) $order['currency'] : null;
        $transactionId = isset($transaction['id']) ? (string) $transaction['id'] : null;
        $topLevelResult = isset($response['result']) ? (string) $response['result'] : null;

        $pending = $evaluated->outcome === NmbPaymentOutcome::Processing;
        $verified = $evaluated->outcome->isVerifiedPaid();

        return new NmbVerificationResult(
            verified: $verified,
            message: $evaluated->message,
            result: $topLevelResult,
            orderId: $orderId,
            transactionId: $transactionId,
            amount: $amount,
            currency: $currency,
            rawResponse: array_merge($response, [
                'nmb_outcome' => $evaluated->outcome->value,
                'nmb_outcome_context' => $evaluated->context,
            ]),
            transientFailure: false,
            pending: $pending,
        );
    }
}
