<?php

namespace App\Payments\Gateways\Nmb\Contracts;

interface NmbCallbackSignatureVerifierInterface
{
    /**
     * @param  array<string, mixed>  $headers
     * @param  array<string, mixed>  $payload
     */
    public function verify(array $headers, string $rawBody, array $payload): bool;

    public function isRequired(): bool;
}
