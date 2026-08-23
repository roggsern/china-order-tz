<?php

namespace App\Services\Payments\Orchestration;

use App\Enums\PaymentProvider;
use App\Enums\PaymentTransactionStatus;
use App\Models\PaymentTransaction;
use App\Payments\Gateways\Snippe\SnippeReplayGuard;
use App\Payments\Gateways\Snippe\SnippeWebhookEventParser;
use App\Payments\Gateways\Snippe\SnippeWebhookRetryableException;
use App\Payments\Gateways\Snippe\SnippeWebhookSignatureVerifier;
use App\Services\Payments\Orchestration\DTOs\PaymentProviderResult;
use App\Services\Payments\Orchestration\Providers\SnippePaymentProvider;
use App\Support\Snippe\SnippePaymentLogger;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Public Snippe webhook handler for Payment Orchestrator transactions.
 * Idempotent: duplicate events do not re-apply side effects.
 */
class SnippeOrchestratorWebhookService
{
    public function __construct(
        private readonly SnippeWebhookSignatureVerifier $signatureVerifier,
        private readonly SnippeReplayGuard $replayGuard,
        private readonly SnippePaymentProvider $snippeProvider,
        private readonly PaymentTransactionCompletionService $completionService,
        private readonly SnippePaymentLogger $logger,
    ) {}

    /**
     * @param  array<string, mixed>  $headers
     * @return array{
     *     accepted: bool,
     *     message: string,
     *     transaction_id: ?string,
     *     event_id: ?string,
     *     event_type: ?string
     * }
     */
    public function handle(array $headers, string $rawBody): array
    {
        $this->signatureVerifier->assertVerified($headers, $rawBody);

        try {
            /** @var array<string, mixed> $payload */
            $payload = json_decode($rawBody, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new HttpException(400, 'Invalid Snippe webhook JSON payload.');
        }

        if (! is_array($payload)) {
            throw new HttpException(400, 'Invalid Snippe webhook payload.');
        }

        try {
            $event = SnippeWebhookEventParser::parse($payload);
        } catch (\InvalidArgumentException) {
            throw new HttpException(400, 'Invalid Snippe webhook event envelope.');
        }

        $eventId = $event['event_id'];
        $eventType = $event['event_type'];
        $data = $event['data'];

        $this->logger->info('snippe.webhook.received', [
            'event_id' => $eventId,
            'event_type' => $eventType,
            'provider_reference' => SnippeWebhookEventParser::providerReference($data),
            'merchant_reference' => SnippeWebhookEventParser::merchantReference($data),
            'payment_transaction_id' => SnippeWebhookEventParser::paymentTransactionId($data),
        ]);

        if ($this->replayGuard->hasSuccessfulDelivery($eventId)) {
            $existing = $this->resolveTransaction($data);

            $this->logger->info('snippe.webhook.duplicate', [
                'event_id' => $eventId,
                'event_type' => $eventType,
                'transaction_id' => $existing?->id,
            ]);

            return [
                'accepted' => true,
                'message' => 'Snippe webhook already processed.',
                'transaction_id' => $existing?->id,
                'event_id' => $eventId,
                'event_type' => $eventType,
            ];
        }

        if (! in_array($eventType, SnippeWebhookEventParser::supportedPaymentEvents(), true)) {
            $this->logger->info('snippe.webhook.ignored', [
                'event_id' => $eventId,
                'event_type' => $eventType,
            ]);

            $this->replayGuard->rememberSuccessfulDelivery($eventId);

            return [
                'accepted' => true,
                'message' => 'Snippe webhook event ignored.',
                'transaction_id' => null,
                'event_id' => $eventId,
                'event_type' => $eventType,
            ];
        }

        $transaction = $this->resolveTransaction($data);

        if ($transaction === null) {
            $this->logger->warning('snippe.webhook.unmatched', [
                'event_id' => $eventId,
                'event_type' => $eventType,
                'provider_reference' => SnippeWebhookEventParser::providerReference($data),
                'merchant_reference' => SnippeWebhookEventParser::merchantReference($data),
                'payload' => SnippeWebhookEventParser::sanitizeForLog($payload),
            ]);

            throw SnippeWebhookRetryableException::transactionNotMatched();
        }

        return DB::transaction(function () use ($transaction, $payload, $eventId, $eventType, $data): array {
            /** @var PaymentTransaction $locked */
            $locked = PaymentTransaction::query()
                ->whereKey($transaction->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->status === PaymentTransactionStatus::Successful) {
                $this->replayGuard->rememberSuccessfulDelivery($eventId);

                $this->logger->info('snippe.webhook.already_settled', [
                    'event_id' => $eventId,
                    'event_type' => $eventType,
                    'transaction_id' => $locked->id,
                ]);

                return [
                    'accepted' => true,
                    'message' => 'Snippe webhook already processed.',
                    'transaction_id' => $locked->id,
                    'event_id' => $eventId,
                    'event_type' => $eventType,
                ];
            }

            if (! $this->referencesMatch($locked, $data)) {
                $this->logger->warning('snippe.webhook.reference_mismatch', [
                    'event_id' => $eventId,
                    'event_type' => $eventType,
                    'transaction_id' => $locked->id,
                    'provider_reference' => SnippeWebhookEventParser::providerReference($data),
                    'merchant_reference' => SnippeWebhookEventParser::merchantReference($data),
                ]);

                throw new HttpException(400, 'Snippe webhook reference mismatch.');
            }

            $locked->forceFill([
                'callback_received_at' => $locked->callback_received_at ?? now(),
            ])->save();

            $result = $this->snippeProvider->handleCallback($locked, $payload);

            if ($this->isTransientVerificationFailure($result)) {
                $this->logger->warning('snippe.webhook.verification_retryable', [
                    'event_id' => $eventId,
                    'event_type' => $eventType,
                    'transaction_id' => $locked->id,
                    'mapped_status' => $result->status->value,
                ]);

                throw SnippeWebhookRetryableException::verificationUnavailable();
            }

            $this->completionService->applyResult($locked, $result);
            $this->replayGuard->rememberSuccessfulDelivery($eventId);

            $this->logger->info('snippe.webhook.processed', [
                'event_id' => $eventId,
                'event_type' => $eventType,
                'transaction_id' => $locked->id,
                'mapped_status' => $result->status->value,
            ]);

            return [
                'accepted' => true,
                'message' => 'Snippe webhook processed.',
                'transaction_id' => $locked->id,
                'event_id' => $eventId,
                'event_type' => $eventType,
            ];
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function resolveTransaction(array $data): ?PaymentTransaction
    {
        $providerReference = SnippeWebhookEventParser::providerReference($data);
        $merchantReference = SnippeWebhookEventParser::merchantReference($data);
        $paymentTransactionId = SnippeWebhookEventParser::paymentTransactionId($data);

        if (filled($providerReference)) {
            $byReference = PaymentTransaction::query()
                ->where('provider', PaymentProvider::Snippe->value)
                ->where('provider_reference', $providerReference)
                ->first();

            if ($byReference !== null) {
                return $byReference;
            }
        }

        if (filled($paymentTransactionId)) {
            $byId = PaymentTransaction::query()
                ->where('provider', PaymentProvider::Snippe->value)
                ->whereKey($paymentTransactionId)
                ->first();

            if ($byId !== null) {
                return $byId;
            }
        }

        if (filled($merchantReference)) {
            return PaymentTransaction::query()
                ->where('provider', PaymentProvider::Snippe->value)
                ->where('merchant_reference', $merchantReference)
                ->first();
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function referencesMatch(PaymentTransaction $transaction, array $data): bool
    {
        $providerReference = SnippeWebhookEventParser::providerReference($data);
        $storedReference = (string) ($transaction->provider_reference ?? '');

        if ($storedReference !== '' && $providerReference !== null && $providerReference !== $storedReference) {
            return false;
        }

        $merchantReference = SnippeWebhookEventParser::merchantReference($data);
        $storedMerchantReference = (string) ($transaction->merchant_reference ?? '');

        if ($storedMerchantReference !== ''
            && $merchantReference !== null
            && $merchantReference !== $storedMerchantReference
        ) {
            return false;
        }

        $paymentTransactionId = SnippeWebhookEventParser::paymentTransactionId($data);
        if ($paymentTransactionId !== null && $paymentTransactionId !== (string) $transaction->id) {
            return false;
        }

        return true;
    }

    private function isTransientVerificationFailure(PaymentProviderResult $result): bool
    {
        $responsePayload = is_array($result->responsePayload) ? $result->responsePayload : [];
        $error = isset($responsePayload['error']) ? (string) $responsePayload['error'] : '';

        if ($error === 'timeout') {
            return true;
        }

        $statusCode = isset($responsePayload['status']) ? (int) $responsePayload['status'] : null;

        return in_array($statusCode, [408, 425, 429, 500, 502, 503, 504], true);
    }
}
