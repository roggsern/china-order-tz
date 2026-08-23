<?php

namespace App\Services\Payments\Orchestration\Providers;

use App\Enums\PaymentProvider;
use App\Enums\PaymentTransactionStatus;
use App\Models\PaymentTransaction;
use App\Payments\Gateways\Snippe\SnippeAmountValidator;
use App\Payments\Gateways\Snippe\SnippeApiClient;
use App\Payments\Gateways\Snippe\SnippeApiException;
use App\Payments\Gateways\Snippe\SnippeConfig;
use App\Payments\Gateways\Snippe\SnippeCustomerIdentityResolver;
use App\Payments\Gateways\Snippe\SnippeIdempotencyKey;
use App\Payments\Gateways\Snippe\SnippePaymentOutcomeEvaluator;
use App\Payments\Gateways\Snippe\SnippePhoneNormalizer;
use App\Payments\Gateways\Snippe\SnippeWebhookEventParser;
use App\Services\Payments\Orchestration\Contracts\PaymentProviderInterface;
use App\Services\Payments\Orchestration\DTOs\PaymentInitiationRequest;
use App\Services\Payments\Orchestration\DTOs\PaymentProviderResult;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Snippe mobile money adapter for the Payment Orchestrator.
 * Credentials/URLs come only from configuration (env → config/payments.php).
 */
class SnippePaymentProvider implements PaymentProviderInterface
{
    public function __construct(
        private readonly SnippeApiClient $apiClient,
        private readonly SnippePaymentOutcomeEvaluator $outcomeEvaluator,
    ) {}

    public function key(): string
    {
        return PaymentProvider::Snippe->value;
    }

    public function initiate(PaymentInitiationRequest $request): PaymentProviderResult
    {
        return $this->initiatePayment($request);
    }

    public function refresh(PaymentTransaction $transaction): PaymentProviderResult
    {
        return $this->verifyPayment($transaction);
    }

    public function verify(PaymentTransaction $transaction): PaymentProviderResult
    {
        return $this->verifyPayment($transaction);
    }

    public function initiatePayment(PaymentInitiationRequest $request): PaymentProviderResult
    {
        if ($configError = $this->initiationConfigurationError()) {
            return $this->failureResult(
                PaymentTransactionStatus::Failed,
                [
                    'provider' => $this->key(),
                    'merchant_reference' => $request->merchantReference,
                    'error' => 'configuration',
                ],
                ['error' => $configError],
                $configError,
            );
        }

        if (! filled($request->phoneNumber)) {
            return $this->failureResult(
                PaymentTransactionStatus::Failed,
                [
                    'provider' => $this->key(),
                    'merchant_reference' => $request->merchantReference,
                    'error' => 'validation',
                ],
                ['error' => 'phone_number_required'],
                'Phone number is required for Snippe mobile money payments.',
            );
        }

        try {
            $normalizedPhone = SnippePhoneNormalizer::normalize($request->phoneNumber);
        } catch (\InvalidArgumentException $exception) {
            return $this->failureResult(
                PaymentTransactionStatus::Failed,
                [
                    'provider' => $this->key(),
                    'merchant_reference' => $request->merchantReference,
                    'error' => 'validation',
                ],
                ['error' => 'invalid_phone'],
                $exception->getMessage(),
            );
        }

        try {
            $amount = SnippeAmountValidator::assertCollectible($request->amount, $request->currency);
        } catch (ValidationException $exception) {
            return $this->failureResult(
                PaymentTransactionStatus::Failed,
                [
                    'provider' => $this->key(),
                    'merchant_reference' => $request->merchantReference,
                    'amount' => $request->amount,
                    'currency' => $request->currency,
                    'error' => 'validation',
                ],
                ['error' => 'amount_validation', 'messages' => $exception->errors()],
                collect($exception->errors())->flatten()->first() ?: 'Invalid payment amount.',
            );
        }

        $order = $request->order;

        try {
            $customer = SnippeCustomerIdentityResolver::resolve($order);
        } catch (ValidationException $exception) {
            return $this->failureResult(
                PaymentTransactionStatus::Failed,
                [
                    'provider' => $this->key(),
                    'merchant_reference' => $request->merchantReference,
                    'error' => 'validation',
                ],
                ['error' => 'customer_identity', 'messages' => $exception->errors()],
                collect($exception->errors())->flatten()->first() ?: 'Customer identity is required for Snippe mobile money payments.',
            );
        }

        $payload = [
            'payment_type' => 'mobile',
            'details' => [
                'amount' => $amount['integer_amount'],
                'currency' => $amount['currency'],
            ],
            'phone_number' => $normalizedPhone,
            'customer' => [
                'firstname' => $customer['firstname'],
                'lastname' => $customer['lastname'],
                'email' => $customer['email'],
            ],
            'metadata' => [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'merchant_reference' => $request->merchantReference,
                'payment_transaction_id' => $request->paymentTransactionId,
            ],
        ];

        $webhookUrl = SnippeConfig::webhookUrl();
        if ($webhookUrl !== '') {
            $payload['webhook_url'] = $webhookUrl;
        }

        $payloadForStorage = $payload;
        $payloadForStorage['phone_number'] = SnippePhoneNormalizer::mask($normalizedPhone);

        $idempotencyKey = $request->paymentTransactionId !== null
            ? SnippeIdempotencyKey::forPaymentTransaction($request->paymentTransactionId)
            : null;

        $requestPayload = [
            'provider' => $this->key(),
            'merchant_reference' => $request->merchantReference,
            'amount' => $request->amount,
            'integer_amount' => $amount['integer_amount'],
            'currency' => $amount['currency'],
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'phone_number' => SnippePhoneNormalizer::mask($normalizedPhone),
            'endpoint' => $this->apiClient->paymentsEndpoint(),
            'idempotency_key' => $idempotencyKey,
            'payload' => $payloadForStorage,
        ];

        try {
            if ($idempotencyKey === null) {
                throw new SnippeApiException('Payment transaction id is required for Snippe idempotency.');
            }

            $response = $this->apiClient->createPayment($payload, $idempotencyKey);
        } catch (SnippeApiException $e) {
            return $this->failureResult(
                PaymentTransactionStatus::Failed,
                $requestPayload,
                [
                    'error' => $e->isTransient() ? 'timeout' : 'api',
                    'message' => $e->getMessage(),
                    'status' => $e->statusCode(),
                    'gateway_response' => $e->gatewayResponse(),
                ],
                $e->getMessage(),
            );
        } catch (Throwable $e) {
            return $this->failureResult(
                PaymentTransactionStatus::Failed,
                $requestPayload,
                ['error' => 'exception', 'message' => $e->getMessage()],
                'Unable to initiate Snippe payment.',
            );
        }

        $data = is_array($response['data'] ?? null) ? $response['data'] : [];
        $reference = isset($data['reference']) ? (string) $data['reference'] : null;
        $externalId = isset($data['id']) ? (string) $data['id'] : null;

        if (! filled($reference)) {
            return $this->failureResult(
                PaymentTransactionStatus::Failed,
                $requestPayload,
                $response,
                'Snippe did not return a payment reference.',
            );
        }

        return new PaymentProviderResult(
            ok: true,
            status: PaymentTransactionStatus::Processing,
            providerReference: $reference,
            externalTransactionId: $externalId,
            requestPayload: $requestPayload,
            responsePayload: $response,
            message: 'Snippe mobile money collection initiated.',
        );
    }

    public function verifyPayment(PaymentTransaction $transaction): PaymentProviderResult
    {
        if ($configError = $this->verificationConfigurationError()) {
            return $this->failureResult(
                PaymentTransactionStatus::Processing,
                [
                    'provider' => $this->key(),
                    'action' => 'verify',
                    'merchant_reference' => $transaction->merchant_reference,
                ],
                ['error' => $configError],
                $configError,
            );
        }

        $reference = (string) ($transaction->provider_reference ?? '');
        if ($reference === '') {
            return $this->failureResult(
                PaymentTransactionStatus::Processing,
                [
                    'provider' => $this->key(),
                    'action' => 'verify',
                    'merchant_reference' => $transaction->merchant_reference,
                ],
                ['error' => 'missing_provider_reference'],
                'Snippe payment reference is not available yet.',
            );
        }

        $requestPayload = [
            'provider' => $this->key(),
            'action' => 'verify',
            'merchant_reference' => $transaction->merchant_reference,
            'provider_reference' => $reference,
            'endpoint' => $this->apiClient->paymentEndpoint($reference),
        ];

        try {
            $response = $this->apiClient->retrievePayment($reference);
        } catch (SnippeApiException $e) {
            return $this->failureResult(
                PaymentTransactionStatus::Processing,
                $requestPayload,
                [
                    'error' => $e->isTransient() ? 'timeout' : 'api',
                    'message' => $e->getMessage(),
                    'status' => $e->statusCode(),
                    'gateway_response' => $e->gatewayResponse(),
                ],
                $e->getMessage(),
            );
        } catch (Throwable $e) {
            return $this->failureResult(
                PaymentTransactionStatus::Processing,
                $requestPayload,
                ['error' => 'exception', 'message' => $e->getMessage()],
                'Unable to verify Snippe payment.',
            );
        }

        $data = is_array($response['data'] ?? null) ? $response['data'] : [];
        $evaluated = $this->outcomeEvaluator->evaluate($data, $transaction);

        $verificationPayload = [
            'provider_status' => $data['status'] ?? null,
            'reference' => $data['reference'] ?? null,
            'external_reference' => $data['external_reference'] ?? null,
            'amount' => $data['amount'] ?? null,
            'outcome' => $evaluated->context,
            'raw' => $this->sanitizeProviderResponse($response),
            'verified_at' => now()->toIso8601String(),
            'verified' => $evaluated->ok,
        ];

        if (strtolower((string) ($data['status'] ?? '')) === 'expired') {
            $verificationPayload['failure_reason'] = 'expired';
        } elseif (isset($data['failure_reason'])) {
            $verificationPayload['failure_reason'] = $data['failure_reason'];
        }

        return new PaymentProviderResult(
            ok: $evaluated->ok,
            status: $evaluated->status,
            providerReference: $reference,
            externalTransactionId: isset($data['id']) ? (string) $data['id'] : $transaction->external_transaction_id,
            requestPayload: $requestPayload,
            responsePayload: $response,
            verificationPayload: $verificationPayload,
            message: $evaluated->message,
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function handleCallback(PaymentTransaction $transaction, array $payload): PaymentProviderResult
    {
        $verified = $this->verifyPayment($transaction);

        $verificationPayload = array_merge(
            $verified->verificationPayload ?? [],
            ['webhook' => SnippeWebhookEventParser::sanitizeForLog($payload)],
        );

        if (strtolower((string) (($verified->verificationPayload ?? [])['provider_status'] ?? '')) === 'expired') {
            $verificationPayload['failure_reason'] = 'expired';
        }

        return new PaymentProviderResult(
            ok: $verified->ok,
            status: $verified->status,
            providerReference: $verified->providerReference ?? $transaction->provider_reference,
            externalTransactionId: $verified->externalTransactionId ?? $transaction->external_transaction_id,
            requestPayload: $verified->requestPayload,
            responsePayload: $verified->responsePayload,
            verificationPayload: $verificationPayload,
            message: $verified->message,
        );
    }

    private function initiationConfigurationError(): ?string
    {
        if (! SnippeConfig::isConfigured()) {
            return 'Snippe payment is not configured. Set SNIPPE_ENABLED, SNIPPE_API_KEY, SNIPPE_BASE_URL, SNIPPE_WEBHOOK_SECRET, and a valid SNIPPE_WEBHOOK_URL.';
        }

        return null;
    }

    private function verificationConfigurationError(): ?string
    {
        if (! SnippeConfig::hasOperationalCredentials()) {
            return 'Snippe payment credentials are not configured. Set SNIPPE_API_KEY and SNIPPE_BASE_URL.';
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $response
     * @return array<string, mixed>
     */
    private function sanitizeProviderResponse(array $response): array
    {
        $data = $response['data'] ?? null;
        if (! is_array($data)) {
            return $response;
        }

        $customer = $data['customer'] ?? null;
        if (is_array($customer) && isset($customer['phone']) && is_string($customer['phone'])) {
            try {
                $data['customer']['phone'] = SnippePhoneNormalizer::mask(
                    SnippePhoneNormalizer::normalize($customer['phone']),
                );
            } catch (\InvalidArgumentException) {
                $data['customer']['phone'] = '***';
            }
            $response['data'] = $data;
        }

        return $response;
    }

    /**
     * @param  array<string, mixed>  $requestPayload
     * @param  array<string, mixed>  $responsePayload
     */
    private function failureResult(
        PaymentTransactionStatus $status,
        array $requestPayload,
        array $responsePayload,
        string $message,
    ): PaymentProviderResult {
        return new PaymentProviderResult(
            ok: false,
            status: $status,
            requestPayload: $requestPayload,
            responsePayload: $responsePayload,
            message: $message,
        );
    }
}
