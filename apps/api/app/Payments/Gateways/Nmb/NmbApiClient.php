<?php

namespace App\Payments\Gateways\Nmb;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class NmbApiClient
{
    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function initiateCheckout(array $payload): array
    {
        $response = Http::withBasicAuth($this->username(), $this->password())
            ->acceptJson()
            ->asJson()
            ->post($this->sessionEndpoint(), $payload);

        return $this->decodeResponse($response);
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

    /**
     * @return array<string, mixed>
     */
    private function decodeResponse(Response $response): array
    {
        $json = $response->json();

        if (is_array($json)) {
            return $json;
        }

        return [
            'result' => 'ERROR',
            'error' => [
                'cause' => 'INVALID_RESPONSE',
                'explanation' => $response->body(),
            ],
        ];
    }
}
