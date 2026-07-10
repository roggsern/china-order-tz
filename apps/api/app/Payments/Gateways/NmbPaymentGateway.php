<?php

namespace App\Payments\Gateways;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Payments\Contracts\AsyncPaymentGatewayInterface;
use App\Payments\Contracts\PaymentGatewayInterface;
use App\Payments\DTOs\InitiatePaymentResult;
use App\Payments\Gateways\Nmb\NmbApiClient;
use App\Payments\Gateways\Nmb\NmbApiException;
use App\Payments\Gateways\Nmb\NmbCheckoutSessionMapper;
use App\Payments\Gateways\Nmb\NmbVerificationMapper;
use App\Payments\Gateways\Nmb\NmbVerificationResult;
use App\Payments\Gateways\Nmb\Requests\NmbInitiateCheckoutRequest;
use App\Payments\Gateways\Nmb\Requests\NmbRetrieveOrderRequest;
use App\Payments\Results\PaymentResult;
use App\Payments\Results\VerificationResult;
use Illuminate\Validation\ValidationException;

class NmbPaymentGateway implements AsyncPaymentGatewayInterface, PaymentGatewayInterface
{
    public function __construct(
        private readonly NmbApiClient $apiClient,
        private readonly NmbCheckoutSessionMapper $sessionMapper,
        private readonly NmbVerificationMapper $verificationMapper,
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

        if (! $this->isConfigured()) {
            return new InitiatePaymentResult(
                success: false,
                status: $payment->status->value,
                message: 'NMB is not configured.',
            );
        }

        $payload = NmbInitiateCheckoutRequest::fromPayment($payment)->toArray();
        $response = $this->apiClient->initiateCheckout($payload);
        $session = $this->sessionMapper->fromResponse($response);

        if (! $session->success) {
            return new InitiatePaymentResult(
                success: false,
                status: $payment->status->value,
                message: $session->message ?? 'Unable to create NMB checkout session.',
                gatewayResponse: $session->rawResponse,
            );
        }

        $payment->update([
            'status' => PaymentStatus::Initiated,
            'gateway_session_id' => $session->sessionId,
            'success_indicator' => $session->successIndicator,
            'gateway_reference' => $session->gatewayReference,
            'transaction_id' => $session->gatewayReference,
            'checkout_url' => $session->checkoutUrl,
            'gateway_response' => $session->rawResponse,
            'initiated_at' => now(),
            'metadata' => array_merge($payment->metadata ?? [], [
                'gateway' => 'nmb',
            ]),
        ]);

        return new InitiatePaymentResult(
            success: true,
            status: PaymentStatus::Initiated->value,
            message: 'Payment session created.',
            checkoutRequestId: $session->sessionId,
            gatewayReference: $session->gatewayReference,
            gatewaySessionId: $session->sessionId,
            successIndicator: $session->successIndicator,
            checkoutUrl: $session->checkoutUrl,
            gatewayResponse: $session->rawResponse,
        );
    }

    public function handleCallback(Payment $payment, array $payload): PaymentResult
    {
        return new PaymentResult(
            success: false,
            status: $payment->status->value,
            message: 'NMB callback handling is not implemented yet.',
        );
    }

    public function verify(Payment $payment): VerificationResult
    {
        $result = $this->verifyPayment($payment);

        return new VerificationResult(
            verified: $result->verified,
            status: $payment->fresh()->status->value,
            message: $result->message,
        );
    }

    public function verifyPayment(Payment $payment): NmbVerificationResult
    {
        if (! $this->isConfigured()) {
            return new NmbVerificationResult(
                verified: false,
                message: 'NMB is not configured.',
            );
        }

        $request = NmbRetrieveOrderRequest::fromPayment($payment);

        try {
            $response = $this->apiClient->retrieveOrder($request->orderId());
        } catch (NmbApiException $exception) {
            return new NmbVerificationResult(
                verified: false,
                message: $exception->getMessage(),
                transientFailure: $exception->isTransient(),
            );
        }

        return $this->verificationMapper->fromResponse($response, $payment);
    }

    private function isConfigured(): bool
    {
        if (! config('services.nmb.enabled')) {
            return false;
        }

        return filled(config('services.nmb.base_url'))
            && filled(config('services.nmb.merchant_id'))
            && filled(config('services.nmb.password'))
            && filled(config('services.nmb.return_url'))
            && filled(config('services.nmb.merchant_name'))
            && filled(config('services.nmb.merchant_url'));
    }

    private function throwValidationError(string $message): never
    {
        throw ValidationException::withMessages([
            'payment' => [$message],
        ]);
    }
}
