<?php

namespace App\Payments\Gateways\Snippe;

use App\Enums\PaymentProvider;
use App\Enums\PaymentTransactionStatus;
use App\Models\PaymentTransaction;

final class SnippePaymentOutcomeEvaluator
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function evaluate(
        array $data,
        PaymentTransaction $transaction,
    ): SnippePaymentOutcome {
        $providerStatus = strtolower((string) ($data['status'] ?? ''));

        $provider = $transaction->provider instanceof PaymentProvider
            ? $transaction->provider->value
            : strtolower((string) ($transaction->provider ?? ''));

        if ($provider !== '' && $provider !== PaymentProvider::Snippe->value) {
            return new SnippePaymentOutcome(
                ok: false,
                status: PaymentTransactionStatus::Processing,
                message: 'Snippe payment provider mismatch.',
                context: ['reason' => 'provider_mismatch', 'provider_status' => $providerStatus],
            );
        }

        $reference = isset($data['reference']) ? (string) $data['reference'] : null;
        $storedReference = (string) ($transaction->provider_reference ?? '');

        if ($storedReference !== '' && $reference !== null && $reference !== $storedReference) {
            return new SnippePaymentOutcome(
                ok: false,
                status: PaymentTransactionStatus::Processing,
                message: 'Snippe payment reference mismatch.',
                context: ['reason' => 'reference_mismatch', 'provider_status' => $providerStatus],
            );
        }

        $amountBlock = is_array($data['amount'] ?? null) ? $data['amount'] : [];
        $amountValue = isset($amountBlock['value']) ? (int) $amountBlock['value'] : null;
        $amountCurrency = isset($amountBlock['currency']) ? strtoupper((string) $amountBlock['currency']) : null;
        $expectedInteger = SnippeAmountValidator::integerAmountFromTransaction((string) $transaction->amount);
        $expectedCurrency = strtoupper((string) $transaction->currency);

        $metadata = is_array($data['metadata'] ?? null) ? $data['metadata'] : [];
        $merchantReference = (string) ($transaction->merchant_reference ?? '');
        $metadataMerchantReference = isset($metadata['merchant_reference'])
            ? trim((string) $metadata['merchant_reference'])
            : '';
        $metadataPaymentTransactionId = isset($metadata['payment_transaction_id'])
            ? trim((string) $metadata['payment_transaction_id'])
            : '';

        if ($metadataMerchantReference !== ''
            && $merchantReference !== ''
            && $metadataMerchantReference !== $merchantReference
        ) {
            return new SnippePaymentOutcome(
                ok: false,
                status: PaymentTransactionStatus::Processing,
                message: 'Snippe merchant reference mismatch.',
                context: ['reason' => 'merchant_reference_mismatch', 'provider_status' => $providerStatus],
            );
        }

        $storedTransactionId = (string) ($transaction->id ?? '');
        if ($metadataPaymentTransactionId !== ''
            && $storedTransactionId !== ''
            && $metadataPaymentTransactionId !== $storedTransactionId
        ) {
            return new SnippePaymentOutcome(
                ok: false,
                status: PaymentTransactionStatus::Processing,
                message: 'Snippe payment transaction mismatch.',
                context: ['reason' => 'payment_transaction_mismatch', 'provider_status' => $providerStatus],
            );
        }

        $internalStatus = $this->mapStatus($providerStatus, $data);

        if ($internalStatus === PaymentTransactionStatus::Successful) {
            if ($amountCurrency !== $expectedCurrency || $expectedCurrency !== 'TZS') {
                return new SnippePaymentOutcome(
                    ok: false,
                    status: PaymentTransactionStatus::Processing,
                    message: 'Snippe payment currency mismatch.',
                    context: ['reason' => 'currency_mismatch', 'provider_status' => $providerStatus],
                );
            }

            if ($amountValue === null || $amountValue !== $expectedInteger) {
                return new SnippePaymentOutcome(
                    ok: false,
                    status: PaymentTransactionStatus::Processing,
                    message: 'Snippe payment amount mismatch.',
                    context: ['reason' => 'amount_mismatch', 'provider_status' => $providerStatus],
                );
            }

            return new SnippePaymentOutcome(
                ok: true,
                status: PaymentTransactionStatus::Successful,
                message: 'Snippe payment verified.',
                context: ['provider_status' => $providerStatus],
            );
        }

        return new SnippePaymentOutcome(
            ok: false,
            status: $internalStatus,
            message: $this->messageForStatus($providerStatus),
            context: [
                'provider_status' => $providerStatus,
                'failure_reason' => $data['failure_reason'] ?? null,
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function mapStatus(string $providerStatus, array $data): PaymentTransactionStatus
    {
        return match ($providerStatus) {
            'pending' => PaymentTransactionStatus::Processing,
            'completed' => PaymentTransactionStatus::Successful,
            'failed' => PaymentTransactionStatus::Failed,
            'expired' => PaymentTransactionStatus::Failed,
            'voided' => PaymentTransactionStatus::Cancelled,
            default => PaymentTransactionStatus::Processing,
        };
    }

    private function messageForStatus(string $providerStatus): string
    {
        return match ($providerStatus) {
            'pending' => 'Snippe payment is pending customer authorization.',
            'failed' => 'Snippe payment failed.',
            'expired' => 'Snippe payment expired.',
            'voided' => 'Snippe payment was voided.',
            default => 'Snippe payment status is not yet final.',
        };
    }
}
