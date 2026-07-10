<?php

namespace App\Payments\Gateways\Nmb;

use App\Payments\Gateways\Nmb\Contracts\NmbCallbackSignatureVerifierInterface;
use App\Support\Nmb\NmbPaymentLogger;

class PendingNmbCallbackSignatureVerifier implements NmbCallbackSignatureVerifierInterface
{
    public function __construct(
        private readonly NmbPaymentLogger $logger,
    ) {}

    /**
     * @param  array<string, mixed>  $headers
     * @param  array<string, mixed>  $payload
     */
    public function verify(array $headers, string $rawBody, array $payload): bool
    {
        if (! $this->isRequired()) {
            return true;
        }

        $this->logger->warning('nmb.callback.signature_not_configured', [
            'message' => 'NMB callback signature verification is required but no verifier is configured.',
        ]);

        return false;
    }

    public function isRequired(): bool
    {
        return (bool) config('services.nmb.webhook.require_signature', false);
    }
}
