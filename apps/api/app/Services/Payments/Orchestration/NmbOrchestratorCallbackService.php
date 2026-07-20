<?php

namespace App\Services\Payments\Orchestration;

use App\Enums\PaymentProvider;
use App\Enums\PaymentTransactionStatus;
use App\Models\PaymentTransaction;
use App\Payments\Gateways\Nmb\Contracts\NmbCallbackSignatureVerifierInterface;
use App\Payments\Gateways\Nmb\NmbCallbackVerifier;
use App\Payments\Gateways\Nmb\NmbReplayGuard;
use App\Services\Payments\Orchestration\Providers\NmbPaymentProvider;
use App\Support\Nmb\NmbPaymentLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Public NMB callback handler for Payment Orchestrator transactions.
 * Idempotent: duplicate callbacks do not re-apply side effects.
 */
class NmbOrchestratorCallbackService
{
    public function __construct(
        private readonly NmbCallbackVerifier $callbackVerifier,
        private readonly NmbCallbackSignatureVerifierInterface $signatureVerifier,
        private readonly NmbReplayGuard $replayGuard,
        private readonly NmbPaymentProvider $nmbProvider,
        private readonly PaymentTransactionCompletionService $completionService,
        private readonly NmbPaymentLogger $logger,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $headers
     * @return array{accepted: bool, message: string, transaction_id: ?string}
     */
    public function handle(array $payload, array $headers = [], string $rawBody = ''): array
    {
        if (! $this->callbackVerifier->isValidPayload($payload)) {
            throw ValidationException::withMessages([
                'callback' => ['Invalid NMB callback payload.'],
            ]);
        }

        if (! $this->signatureVerifier->verify($headers, $rawBody, $payload)) {
            $this->logger->warning('nmb.orchestrator.callback.signature_rejected', [
                'session_id' => $this->callbackVerifier->extractSessionId($payload),
                'order_reference' => $this->callbackVerifier->extractOrderReference($payload),
            ]);

            throw ValidationException::withMessages([
                'callback' => ['Invalid NMB callback signature.'],
            ]);
        }

        $sessionId = $this->callbackVerifier->extractSessionId($payload);
        $orderReference = $this->callbackVerifier->extractOrderReference($payload);

        if ($this->replayGuard->isDuplicate($payload)) {
            $existing = $this->resolveTransaction($sessionId, $orderReference);

            $this->logger->info('nmb.orchestrator.callback.replay_detected', [
                'session_id' => $sessionId,
                'order_reference' => $orderReference,
                'transaction_id' => $existing?->id,
            ]);

            return [
                'accepted' => true,
                'message' => 'NMB callback already processed.',
                'transaction_id' => $existing?->id,
            ];
        }

        $transaction = $this->resolveTransaction($sessionId, $orderReference);

        if ($transaction === null) {
            $this->logger->warning('nmb.orchestrator.callback.unmatched', [
                'session_id' => $sessionId,
                'order_reference' => $orderReference,
                'payload' => $this->callbackVerifier->sanitizeForLog($payload),
            ]);

            // Acknowledge to avoid provider retry storms; nothing to update.
            $this->replayGuard->remember($payload);

            return [
                'accepted' => true,
                'message' => 'NMB callback received. Transaction not matched.',
                'transaction_id' => null,
            ];
        }

        return DB::transaction(function () use ($transaction, $payload, $sessionId, $orderReference): array {
            /** @var PaymentTransaction $locked */
            $locked = PaymentTransaction::query()
                ->whereKey($transaction->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->status === PaymentTransactionStatus::Successful) {
                $this->replayGuard->remember($payload);

                return [
                    'accepted' => true,
                    'message' => 'NMB callback already processed.',
                    'transaction_id' => $locked->id,
                ];
            }

            $locked->forceFill([
                'callback_received_at' => $locked->callback_received_at ?? now(),
                'response_payload' => $payload,
            ])->save();

            $result = $this->nmbProvider->handleCallback($locked, $payload);
            $this->completionService->applyResult($locked, $result);
            $this->replayGuard->remember($payload);

            $this->logger->info('nmb.orchestrator.callback.processed', [
                'session_id' => $sessionId,
                'order_reference' => $orderReference,
                'transaction_id' => $locked->id,
                'status' => $result->status->value,
            ]);

            return [
                'accepted' => true,
                'message' => 'NMB callback processed.',
                'transaction_id' => $locked->id,
            ];
        });
    }

    private function resolveTransaction(?string $sessionId, ?string $orderReference): ?PaymentTransaction
    {
        if (filled($sessionId)) {
            $bySession = PaymentTransaction::query()
                ->where('provider', PaymentProvider::Nmb->value)
                ->where('provider_reference', $sessionId)
                ->first();

            if ($bySession !== null) {
                return $bySession;
            }
        }

        if (filled($orderReference)) {
            return PaymentTransaction::query()
                ->where('provider', PaymentProvider::Nmb->value)
                ->where('merchant_reference', $orderReference)
                ->first();
        }

        return null;
    }
}
