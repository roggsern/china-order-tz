<?php

namespace App\Payments\Gateways;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Payments\Contracts\AsyncPaymentGatewayInterface;
use App\Payments\Contracts\PaymentGatewayInterface;
use App\Payments\DTOs\InitiatePaymentResult;
use App\Payments\Gateways\Nmb\NmbApiClient;
use App\Payments\Gateways\Nmb\NmbCallbackVerifier;
use App\Payments\Gateways\Nmb\NmbPayloadMapper;
use App\Payments\Results\PaymentResult;
use App\Payments\Results\VerificationResult;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class NmbPaymentGateway implements AsyncPaymentGatewayInterface, PaymentGatewayInterface
{
    public function __construct(
        private readonly NmbApiClient $apiClient,
        private readonly NmbCallbackVerifier $callbackVerifier,
        private readonly NmbPayloadMapper $payloadMapper,
    ) {}

    public function process(Payment $payment): PaymentResult
    {
        return new PaymentResult(
            success: false,
            status: $payment->status->value,
            message: 'NMB payments must be initiated asynchronously.',
        );
    }

    public function refund(Payment $payment): PaymentResult
    {
        return new PaymentResult(
            success: false,
            status: $payment->status->value,
            message: 'NMB payment gateway refund is not implemented yet.',
        );
    }

    public function initiate(Payment $payment): InitiatePaymentResult
    {
        if (! in_array($payment->status, [PaymentStatus::Pending, PaymentStatus::Initiated, PaymentStatus::Failed], true)) {
            $this->throwValidationError('Payment cannot be initiated from its current status.');
        }

        $result = $this->apiClient->createSession($payment);

        if (! $result->success) {
            return $result;
        }

        $gatewayReference = $result->gatewayReference ?? $result->checkoutRequestId;

        $payment->update([
            'status' => PaymentStatus::Initiated,
            'gateway_reference' => $gatewayReference,
            'transaction_id' => $gatewayReference,
            'checkout_url' => $result->checkoutUrl,
            'gateway_response' => $result->gatewayResponse,
            'initiated_at' => now(),
            'metadata' => array_merge($payment->metadata ?? [], [
                'gateway' => 'nmb',
            ]),
        ]);

        return $result;
    }

    public function handleCallback(Payment $payment, array $payload): PaymentResult
    {
        if (! $this->callbackVerifier->verify($payload)) {
            return new PaymentResult(
                success: false,
                status: $payment->status->value,
                message: 'Callback verification failed.',
            );
        }

        if ($payment->status === PaymentStatus::Paid) {
            return new PaymentResult(
                success: true,
                status: PaymentStatus::Paid->value,
                message: 'Payment already completed.',
            );
        }

        if (! in_array($payment->status, [PaymentStatus::Pending, PaymentStatus::Initiated], true)) {
            $this->throwValidationError('Payment cannot be updated from its current status.');
        }

        if (! $this->payloadMapper->isSuccessfulSimulation($payload)) {
            $payment->update(['status' => PaymentStatus::Failed]);

            return new PaymentResult(
                success: false,
                status: PaymentStatus::Failed->value,
                message: 'Payment failed.',
            );
        }

        return DB::transaction(function () use ($payment) {
            $payment->update([
                'status' => PaymentStatus::Paid,
                'paid_at' => now(),
            ]);

            return new PaymentResult(
                success: true,
                status: PaymentStatus::Paid->value,
                message: 'Payment processed successfully.',
            );
        });
    }

    public function verify(Payment $payment): VerificationResult
    {
        return new VerificationResult(
            verified: $payment->status === PaymentStatus::Paid,
            status: $payment->status->value,
            message: 'NMB payment verification stub.',
        );
    }

    private function throwValidationError(string $message): never
    {
        throw ValidationException::withMessages([
            'payment' => [$message],
        ]);
    }
}
