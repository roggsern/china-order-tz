<?php

namespace App\Payments\Gateways\Snippe;

class SnippeApiClient
{
    public function __construct(
        private readonly SnippeHttpClient $httpClient,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function createPayment(array $payload, string $idempotencyKey): array
    {
        return $this->httpClient->post(
            $this->paymentsEndpoint(),
            $payload,
            $idempotencyKey,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function retrievePayment(string $reference): array
    {
        return $this->httpClient->get($this->paymentEndpoint($reference));
    }

    public function paymentsEndpoint(): string
    {
        return SnippeConfig::baseUrl().'/v1/payments';
    }

    public function paymentEndpoint(string $reference): string
    {
        return SnippeConfig::baseUrl().'/v1/payments/'.rawurlencode($reference);
    }
}
