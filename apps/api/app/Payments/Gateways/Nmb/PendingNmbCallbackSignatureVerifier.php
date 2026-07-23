<?php

namespace App\Payments\Gateways\Nmb;

use App\Payments\Gateways\Nmb\Contracts\NmbCallbackSignatureVerifierInterface;
use App\Support\Nmb\NmbPaymentLogger;

/**
 * @deprecated Use NmbWebhookSignatureVerifier. Kept as a thin alias for old bindings/tests.
 */
class PendingNmbCallbackSignatureVerifier implements NmbCallbackSignatureVerifierInterface
{
    private readonly NmbWebhookSignatureVerifier $inner;

    public function __construct(NmbPaymentLogger $logger)
    {
        $this->inner = new NmbWebhookSignatureVerifier($logger);
    }

    /**
     * @param  array<string, mixed>  $headers
     * @param  array<string, mixed>  $payload
     */
    public function verify(array $headers, string $rawBody, array $payload): bool
    {
        return $this->inner->verify($headers, $rawBody, $payload);
    }

    public function isRequired(): bool
    {
        return $this->inner->isRequired();
    }
}
