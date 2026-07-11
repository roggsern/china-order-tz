<?php

namespace App\Payments\Gateways\Nmb;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class NmbHttpClient
{
    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function post(string $url, array $payload, callable $configureAuth): array
    {
        return $this->request('post', $url, $configureAuth, $payload, retry: false);
    }

    /**
     * @return array<string, mixed>
     */
    public function get(string $url, callable $configureAuth): array
    {
        return $this->request('get', $url, $configureAuth, payload: null, retry: true);
    }

    /**
     * @param  array<string, mixed>|null  $payload
     * @return array<string, mixed>
     */
    private function request(
        string $method,
        string $url,
        callable $configureAuth,
        ?array $payload,
        bool $retry,
    ): array {
        $attempts = $retry ? max(1, (int) NmbConfig::get('http_retry_times', 2) + 1) : 1;
        $lastException = null;

        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            try {
                $this->logRequestStart($method, $url);

                $pendingRequest = Http::timeout((int) NmbConfig::get('http_timeout', 30))
                    ->connectTimeout((int) NmbConfig::get('http_connect_timeout', 10))
                    ->acceptJson();

                $pendingRequest = $configureAuth($pendingRequest);

                $response = $method === 'post'
                    ? $pendingRequest->asJson()->post($url, $payload ?? [])
                    : $pendingRequest->get($url);

                $this->logResponseStatus($method, $url, $response->status());

                return $this->decodeResponse($response);
            } catch (ConnectionException $exception) {
                $lastException = new NmbApiException(
                    message: 'Unable to reach NMB API.',
                    transient: true,
                    previous: $exception,
                );

                if ($attempt >= $attempts) {
                    throw $lastException;
                }

                usleep($attempt * 200_000);
            }
        }

        throw $lastException ?? new NmbApiException('Unable to reach NMB API.', transient: true);
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeResponse(Response $response): array
    {
        if ($response->status() === 401) {
            throw new NmbApiException(
                message: 'NMB API authentication failed.',
                transient: false,
                statusCode: 401,
                gatewayResponse: $this->gatewayResponseFrom($response),
            );
        }

        if ($response->successful()) {
            $json = $response->json();

            if (is_array($json)) {
                return $json;
            }

            throw new NmbApiException(
                message: 'NMB API returned an invalid JSON response.',
                transient: false,
                statusCode: $response->status(),
                gatewayResponse: [
                    'result' => 'ERROR',
                    'error' => [
                        'cause' => 'INVALID_RESPONSE',
                        'explanation' => $response->body(),
                    ],
                ],
            );
        }

        $status = $response->status();
        $transient = in_array($status, [408, 425, 429, 500, 502, 503, 504], true);
        $message = (string) ($response->json('error.explanation') ?? $response->body() ?: 'NMB API request failed.');

        throw new NmbApiException(
            message: $message,
            transient: $transient,
            statusCode: $status,
            gatewayResponse: $this->gatewayResponseFrom($response),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function gatewayResponseFrom(Response $response): array
    {
        $json = $response->json();

        if (is_array($json)) {
            return $json;
        }

        return [
            'result' => 'ERROR',
            'error' => [
                'cause' => 'HTTP_'.$response->status(),
                'explanation' => $response->body() ?: 'NMB API request failed.',
            ],
        ];
    }

    private function logRequestStart(string $method, string $url): void
    {
        Log::channel($this->logChannel())->info('nmb.api.request_started', [
            'domain' => 'nmb_payments',
            'method' => strtoupper($method),
            'url' => $url,
        ]);
    }

    private function logResponseStatus(string $method, string $url, int $status): void
    {
        Log::channel($this->logChannel())->info('nmb.api.response_received', [
            'domain' => 'nmb_payments',
            'method' => strtoupper($method),
            'url' => $url,
            'status' => $status,
        ]);
    }

    private function logChannel(): string
    {
        return (string) NmbConfig::get('log_channel', 'stack');
    }
}
