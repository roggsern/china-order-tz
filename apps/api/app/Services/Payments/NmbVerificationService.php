<?php

namespace App\Services\Payments;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Payments\Gateways\Nmb\NmbCallbackVerifier;
use App\Payments\Gateways\Nmb\NmbVerificationResult;
use App\Payments\Gateways\NmbPaymentGateway;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class NmbVerificationService
{
    /**
     * @var array<int, PaymentStatus>
     */
    private const REJECTED_STATUSES = [
        PaymentStatus::Paid,
        PaymentStatus::Expired,
        PaymentStatus::Cancelled,
    ];

    public function __construct(
        private readonly NmbPaymentGateway $paymentGateway,
        private readonly NmbCallbackVerifier $callbackVerifier,
    ) {}

    public function verify(Payment $payment): NmbVerificationResult
    {
        $this->validatePayment($payment);

        return DB::transaction(function () use ($payment): NmbVerificationResult {
            /** @var Payment $lockedPayment */
            $lockedPayment = Payment::query()
                ->whereKey($payment->id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->validatePayment($lockedPayment);

            if ($existing = $this->existingVerification($lockedPayment)) {
                return $existing;
            }

            $result = $this->paymentGateway->verifyPayment($lockedPayment);
            $this->persistVerification($lockedPayment, $result);

            if (! $result->verified && $lockedPayment->status === PaymentStatus::Initiated) {
                $lockedPayment->update([
                    'status' => PaymentStatus::Failed,
                ]);
            }

            return $result;
        });
    }

    private function validatePayment(Payment $payment): void
    {
        if (! in_array($payment->method, [PaymentMethod::Nmb, PaymentMethod::BankTransfer], true)) {
            $this->throwValidationError('Payment is not an NMB payment.');
        }

        if (in_array($payment->status, self::REJECTED_STATUSES, true)) {
            $this->throwValidationError("Payment cannot be verified while status is {$payment->status->value}.");
        }

        if (! filled($payment->reference)) {
            $this->throwValidationError('Payment reference is required for NMB verification.');
        }
    }

    private function existingVerification(Payment $payment): ?NmbVerificationResult
    {
        $stored = is_array($payment->metadata['nmb_verification'] ?? null)
            ? $payment->metadata['nmb_verification']
            : null;

        if ($stored === null || ! ($stored['verified'] ?? false)) {
            return null;
        }

        return new NmbVerificationResult(
            verified: true,
            message: (string) ($stored['message'] ?? 'NMB transaction already verified.'),
            result: isset($stored['result']) ? (string) $stored['result'] : null,
            orderId: isset($stored['order_id']) ? (string) $stored['order_id'] : null,
            transactionId: isset($stored['transaction_id']) ? (string) $stored['transaction_id'] : null,
            amount: isset($stored['amount']) ? (string) $stored['amount'] : null,
            currency: isset($stored['currency']) ? (string) $stored['currency'] : null,
            rawResponse: is_array($stored['payload'] ?? null) ? $stored['payload'] : [],
        );
    }

    private function persistVerification(Payment $payment, NmbVerificationResult $result): void
    {
        $metadata = $payment->metadata ?? [];
        $gatewayResponse = $payment->gateway_response ?? [];

        $payment->update([
            'metadata' => array_merge($metadata, [
                'nmb_verification' => [
                    'verified_at' => now()->toIso8601String(),
                    'verified' => $result->verified,
                    'result' => $result->result,
                    'message' => $result->message,
                    'order_id' => $result->orderId,
                    'transaction_id' => $result->transactionId,
                    'amount' => $result->amount,
                    'currency' => $result->currency,
                    'payload' => $this->callbackVerifier->sanitizeForLog($result->rawResponse),
                ],
            ]),
            'gateway_response' => array_merge($gatewayResponse, [
                'verification' => $this->callbackVerifier->sanitizeForLog($result->rawResponse),
            ]),
            'transaction_id' => $result->transactionId ?? $payment->transaction_id,
        ]);
    }

    private function throwValidationError(string $message): never
    {
        throw ValidationException::withMessages([
            'payment' => [$message],
        ]);
    }
}
