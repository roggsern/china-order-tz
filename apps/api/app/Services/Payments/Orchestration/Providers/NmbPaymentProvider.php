<?php

namespace App\Services\Payments\Orchestration\Providers;

use App\Enums\PaymentProvider;
use App\Enums\PaymentTransactionStatus;
use App\Models\PaymentTransaction;
use App\Payments\Gateways\Nmb\NmbApiClient;
use App\Payments\Gateways\Nmb\NmbApiException;
use App\Payments\Gateways\Nmb\NmbCheckoutSessionMapper;
use App\Payments\Gateways\Nmb\NmbConfig;
use App\Services\Payments\Orchestration\Contracts\PaymentProviderInterface;
use App\Services\Payments\Orchestration\DTOs\PaymentInitiationRequest;
use App\Services\Payments\Orchestration\DTOs\PaymentProviderResult;
use Throwable;

/**
 * Production NMB adapter for the Payment Orchestrator.
 * Credentials/URLs come only from configuration (env → config/payments.php / services.nmb).
 */
class NmbPaymentProvider implements PaymentProviderInterface
{
    public function __construct(
        private readonly NmbApiClient $apiClient,
        private readonly NmbCheckoutSessionMapper $sessionMapper,
    ) {}

    public function key(): string
    {
        return PaymentProvider::Nmb->value;
    }

    public function initiate(PaymentInitiationRequest $request): PaymentProviderResult
    {
        return $this->initiatePayment($request);
    }

    public function refresh(PaymentTransaction $transaction): PaymentProviderResult
    {
        return $this->refreshPayment($transaction);
    }

    public function verify(PaymentTransaction $transaction): PaymentProviderResult
    {
        return $this->verifyPayment($transaction);
    }

    public function initiatePayment(PaymentInitiationRequest $request): PaymentProviderResult
    {
        if ($configError = $this->configurationError()) {
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

        $payload = [
            'apiOperation' => 'INITIATE_CHECKOUT',
            'interaction' => [
                'operation' => 'PURCHASE',
                'returnUrl' => (string) NmbConfig::get('return_url'),
                'merchant' => [
                    'name' => (string) NmbConfig::get('merchant_name'),
                    'url' => (string) NmbConfig::get('merchant_url'),
                ],
            ],
            'order' => [
                'id' => $request->merchantReference,
                'reference' => $request->merchantReference,
                'amount' => number_format((float) $request->amount, 2, '.', ''),
                'currency' => strtoupper($request->currency),
                'description' => 'China Order TZ payment '.$request->order->order_number,
            ],
        ];

        $requestPayload = [
            'provider' => $this->key(),
            'merchant_reference' => $request->merchantReference,
            'amount' => $request->amount,
            'currency' => $request->currency,
            'order_id' => $request->order->id,
            'order_number' => $request->order->order_number,
            'endpoint' => $this->apiClient->sessionEndpoint(),
            'merchant_id' => NmbConfig::merchantId(),
            'username_configured' => filled(NmbConfig::username()),
            'password_configured' => filled(NmbConfig::password()),
            'payload' => $payload,
        ];

        try {
            $response = $this->apiClient->initiateCheckout($payload);
        } catch (NmbApiException $e) {
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
                'Unable to initiate NMB payment.',
            );
        }

        $session = $this->sessionMapper->fromResponse($response);

        if (! $session->success) {
            return $this->failureResult(
                PaymentTransactionStatus::Failed,
                $requestPayload,
                $response,
                $session->message ?? 'Unable to create NMB checkout session.',
            );
        }

        return new PaymentProviderResult(
            ok: true,
            status: PaymentTransactionStatus::Processing,
            providerReference: $session->sessionId,
            checkoutUrl: $session->checkoutUrl,
            successIndicator: $session->successIndicator,
            requestPayload: $requestPayload,
            responsePayload: $response,
            message: 'NMB checkout session created.',
        );
    }

    public function refreshPayment(PaymentTransaction $transaction): PaymentProviderResult
    {
        return $this->verifyPayment($transaction);
    }

    public function verifyPayment(PaymentTransaction $transaction): PaymentProviderResult
    {
        if ($configError = $this->configurationError()) {
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

        $orderId = (string) $transaction->merchant_reference;
        $requestPayload = [
            'provider' => $this->key(),
            'action' => 'verify',
            'merchant_reference' => $orderId,
            'endpoint' => $this->apiClient->orderEndpoint($orderId),
        ];

        try {
            $response = $this->apiClient->retrieveOrder($orderId);
        } catch (NmbApiException $e) {
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
                'Unable to verify NMB payment.',
            );
        }

        return $this->mapVerificationResponse($transaction, $response, $requestPayload);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function handleCallback(PaymentTransaction $transaction, array $payload): PaymentProviderResult
    {
        $result = strtoupper((string) ($payload['result'] ?? ''));
        $session = is_array($payload['session'] ?? null) ? $payload['session'] : [];
        $transactionBlock = is_array($payload['transaction'] ?? null) ? $payload['transaction'] : [];

        $externalId = isset($transactionBlock['id'])
            ? (string) $transactionBlock['id']
            : ($payload['transactionId'] ?? null);

        if (in_array($result, ['FAILURE', 'FAILED', 'ERROR', 'CANCELLED', 'CANCELED'], true)) {
            return new PaymentProviderResult(
                ok: false,
                status: $result === 'CANCELLED' || $result === 'CANCELED'
                    ? PaymentTransactionStatus::Cancelled
                    : PaymentTransactionStatus::Failed,
                providerReference: $transaction->provider_reference
                    ?? (isset($session['id']) ? (string) $session['id'] : null),
                externalTransactionId: $externalId ? (string) $externalId : null,
                responsePayload: $payload,
                verificationPayload: ['callback' => $payload],
                message: 'NMB callback reported a non-successful payment.',
            );
        }

        // Successful-looking callbacks still require retrieveOrder verification.
        $verified = $this->verifyPayment($transaction);

        return new PaymentProviderResult(
            ok: $verified->ok,
            status: $verified->status,
            providerReference: $verified->providerReference ?? $transaction->provider_reference,
            externalTransactionId: $verified->externalTransactionId ?? ($externalId ? (string) $externalId : null),
            checkoutUrl: $transaction->checkout_url,
            successIndicator: $transaction->success_indicator,
            requestPayload: $verified->requestPayload,
            responsePayload: $payload,
            verificationPayload: array_merge(
                $verified->verificationPayload ?? [],
                ['callback' => $payload],
            ),
            message: $verified->message,
        );
    }

    /**
     * @param  array<string, mixed>  $response
     * @param  array<string, mixed>  $requestPayload
     */
    private function mapVerificationResponse(
        PaymentTransaction $transaction,
        array $response,
        array $requestPayload,
    ): PaymentProviderResult {
        $result = isset($response['result']) ? (string) $response['result'] : null;
        $order = is_array($response['order'] ?? null) ? $response['order'] : [];
        $txn = is_array($response['transaction'] ?? null) ? $response['transaction'] : [];

        $orderId = isset($order['id']) ? (string) $order['id'] : null;
        $amount = isset($order['amount']) ? (string) $order['amount'] : null;
        $currency = isset($order['currency']) ? (string) $order['currency'] : null;
        $transactionId = isset($txn['id']) ? (string) $txn['id'] : null;

        $verificationPayload = [
            'result' => $result,
            'order_id' => $orderId,
            'transaction_id' => $transactionId,
            'amount' => $amount,
            'currency' => $currency,
            'raw' => $response,
            'verified_at' => now()->toIso8601String(),
        ];

        if (strtoupper($result ?? '') !== 'SUCCESS') {
            $failedStatuses = ['FAILURE', 'FAILED', 'ERROR'];
            $status = in_array(strtoupper($result ?? ''), $failedStatuses, true)
                ? PaymentTransactionStatus::Failed
                : PaymentTransactionStatus::Processing;

            return new PaymentProviderResult(
                ok: false,
                status: $status,
                providerReference: $transaction->provider_reference,
                externalTransactionId: $transactionId,
                requestPayload: $requestPayload,
                responsePayload: $response,
                verificationPayload: $verificationPayload,
                message: (string) (
                    $response['error']['explanation']
                    ?? $response['error']['cause']
                    ?? 'NMB order verification did not succeed.'
                ),
            );
        }

        if ($orderId !== null && $orderId !== (string) $transaction->merchant_reference) {
            return new PaymentProviderResult(
                ok: false,
                status: PaymentTransactionStatus::Failed,
                providerReference: $transaction->provider_reference,
                externalTransactionId: $transactionId,
                requestPayload: $requestPayload,
                responsePayload: $response,
                verificationPayload: $verificationPayload,
                message: 'Verified order id does not match merchant reference.',
            );
        }

        if ($amount !== null && bccomp($amount, number_format((float) $transaction->amount, 2, '.', ''), 2) !== 0) {
            return new PaymentProviderResult(
                ok: false,
                status: PaymentTransactionStatus::Failed,
                providerReference: $transaction->provider_reference,
                externalTransactionId: $transactionId,
                requestPayload: $requestPayload,
                responsePayload: $response,
                verificationPayload: $verificationPayload,
                message: 'Verified amount does not match transaction amount.',
            );
        }

        if ($currency !== null && strtoupper($currency) !== strtoupper((string) $transaction->currency)) {
            return new PaymentProviderResult(
                ok: false,
                status: PaymentTransactionStatus::Failed,
                providerReference: $transaction->provider_reference,
                externalTransactionId: $transactionId,
                requestPayload: $requestPayload,
                responsePayload: $response,
                verificationPayload: $verificationPayload,
                message: 'Verified currency does not match transaction currency.',
            );
        }

        $verificationPayload['verified'] = true;

        return new PaymentProviderResult(
            ok: true,
            status: PaymentTransactionStatus::Successful,
            providerReference: $transaction->provider_reference,
            externalTransactionId: $transactionId,
            checkoutUrl: $transaction->checkout_url,
            successIndicator: $transaction->success_indicator,
            requestPayload: $requestPayload,
            responsePayload: $response,
            verificationPayload: $verificationPayload,
            message: 'NMB payment verified successfully.',
        );
    }

    private function configurationError(): ?string
    {
        if (! filled(NmbConfig::get('base_url'))
            || ! filled(NmbConfig::merchantId())
            || ! filled(NmbConfig::password())
        ) {
            return 'NMB payment is not configured. Set NMB_BASE_URL, NMB_MERCHANT_ID, and NMB_PASSWORD.';
        }

        return null;
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
