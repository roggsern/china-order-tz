<?php

namespace App\Services\Payments;

use App\Models\Payment;
use App\Payments\Gateways\Nmb\NmbCallbackVerifier;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class NmbCallbackService
{
    public function __construct(
        private readonly NmbCallbackVerifier $callbackVerifier,
        private readonly NmbVerificationService $verificationService,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     * @return array{accepted: bool, message: string, payment_id: ?string}
     */
    public function handle(array $payload): array
    {
        if (! $this->callbackVerifier->isValidPayload($payload)) {
            $this->throwValidationError('Invalid NMB callback payload.');
        }

        $sessionId = $this->callbackVerifier->extractSessionId($payload);
        $orderReference = $this->callbackVerifier->extractOrderReference($payload);

        Log::info('NMB callback received', [
            'session_id' => $sessionId,
            'order_reference' => $orderReference,
            'result' => $payload['result'] ?? null,
            'payload' => $this->callbackVerifier->sanitizeForLog($payload),
        ]);

        $payment = $this->resolvePayment($payload);

        if ($payment !== null) {
            $this->storeCallback($payment, $payload);
            $payment = $payment->fresh();

            if (config('services.nmb.auto_verify_after_callback')) {
                $this->verificationService->verify($payment);
            }
        }

        return [
            'accepted' => true,
            'message' => $payment !== null
                ? 'NMB callback received and stored.'
                : 'NMB callback received. Payment not matched.',
            'payment_id' => $payment?->id,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function resolvePayment(array $payload): ?Payment
    {
        if ($sessionId = $this->callbackVerifier->extractSessionId($payload)) {
            $payment = Payment::query()
                ->where('gateway_session_id', $sessionId)
                ->first();

            if ($payment !== null) {
                return $payment;
            }
        }

        if ($reference = $this->callbackVerifier->extractOrderReference($payload)) {
            return Payment::query()
                ->where('reference', $reference)
                ->first();
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function storeCallback(Payment $payment, array $payload): void
    {
        $metadata = $payment->metadata ?? [];
        $callbacks = is_array($metadata['nmb_callbacks'] ?? null) ? $metadata['nmb_callbacks'] : [];

        $callbacks[] = [
            'received_at' => now()->toIso8601String(),
            'session_id' => $this->callbackVerifier->extractSessionId($payload),
            'order_reference' => $this->callbackVerifier->extractOrderReference($payload),
            'result' => isset($payload['result']) ? (string) $payload['result'] : null,
            'payload' => $this->callbackVerifier->sanitizeForLog($payload),
        ];

        $payment->update([
            'metadata' => array_merge($metadata, [
                'nmb_callbacks' => $callbacks,
            ]),
        ]);
    }

    private function throwValidationError(string $message): never
    {
        throw ValidationException::withMessages([
            'callback' => [$message],
        ]);
    }
}
