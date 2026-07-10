<?php

namespace App\Payments\Gateways\Nmb;

class NmbApiClient
{
    public function __construct(
        private readonly NmbHttpClient $httpClient,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function initiateCheckout(array $payload): array
    {
        return $this->httpClient->post(
            $this->sessionEndpoint(),
            $payload,
            fn ($request) => $request->withBasicAuth($this->username(), $this->password()),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function retrieveOrder(string $orderId): array
    {
        return $this->httpClient->get(
            $this->orderEndpoint($orderId),
            fn ($request) => $request->withBasicAuth($this->username(), $this->password()),
        );
    }

    public function orderEndpoint(string $orderId): string
    {
        $baseUrl = rtrim((string) config('services.nmb.base_url'), '/');
        $version = (string) config('services.nmb.api_version', '85');
        $merchantId = (string) config('services.nmb.merchant_id');

        return "{$baseUrl}/api/rest/version/{$version}/merchant/{$merchantId}/order/{$orderId}";
    }

    public function sessionEndpoint(): string
    {
        $baseUrl = rtrim((string) config('services.nmb.base_url'), '/');
        $version = (string) config('services.nmb.api_version', '85');
        $merchantId = (string) config('services.nmb.merchant_id');

        return "{$baseUrl}/api/rest/version/{$version}/merchant/{$merchantId}/session";
    }

    public function username(): string
    {
        $configuredUsername = config('services.nmb.username');

        if (filled($configuredUsername)) {
            return (string) $configuredUsername;
        }

        $merchantId = (string) config('services.nmb.merchant_id');

        return "merchant.{$merchantId}";
    }

    public function password(): string
    {
        return (string) config('services.nmb.password');
    }
}
