<?php

namespace App\Payments\Gateways\Snippe;

use App\Support\Snippe\SnippePaymentLogger;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class SnippeHttpClient
{
    public function __construct(
        private readonly SnippePaymentLogger $logger,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function post(string $url, array $payload, ?string $idempotencyKey = null): array
    {
        return $this->request('post', $url, $payload, $idempotencyKey, retry: false);
    }

    /**
     * @return array<string, mixed>
     */
    public function get(string $url): array
    {
        return $this->request('get', $url, null, null, retry: true);
    }

    /**
     * @param  array<string, mixed>|null  $payload
     * @return array<string, mixed>
     */
    private function request(
        string $method,
        string $url,
        ?array $payload,
        ?string $idempotencyKey,
        bool $retry,
    ): array {
        $attempts = $retry ? max(1, (int) SnippeConfig::get('http_retry_times', 2) + 1) : 1;
        $lastException = null;

        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            try {
                $this->logger->info('snippe.api.request_started', [
                    'method' => strtoupper($method),
                    'url' => $url,
                    'idempotency_key_present' => $idempotencyKey !== null,
                ]);

                $pendingRequest = Http::timeout((int) SnippeConfig::get('http_timeout', 30))
                    ->connectTimeout((int) SnippeConfig::get('http_connect_timeout', 10))
                    ->acceptJson()
                    ->withToken(SnippeConfig::apiKey());

                if ($idempotencyKey !== null && $idempotencyKey !== '') {
                    $pendingRequest = $pendingRequest->withHeaders([
                        'Idempotency-Key' => $idempotencyKey,
                    ]);
                }

                $response = $method === 'post'
                    ? $pendingRequest->asJson()->post($url, $payload ?? [])
                    : $pendingRequest->get($url);

                $this->logger->info('snippe.api.response_received', [
                    'method' => strtoupper($method),
                    'url' => $url,
                    'status' => $response->status(),
                ]);

                return $this->decodeResponse($response);
            } catch (ConnectionException $exception) {
                $lastException = new SnippeApiException(
                    message: 'Unable to reach Snippe API.',
                    transient: true,
                    previous: $exception,
                );

                if ($attempt >= $attempts) {
                    throw $lastException;
                }

                usleep($attempt * 200_000);
            }
        }

        throw $lastException ?? new SnippeApiException('Unable to reach Snippe API.', transient: true);
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeResponse(Response $response): array
    {
        if ($response->status() === 401) {
            throw new SnippeApiException(
                message: 'Snippe API authentication failed.',
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

            throw new SnippeApiException(
                message: 'Snippe API returned an invalid JSON response.',
                transient: false,
                statusCode: $response->status(),
                gatewayResponse: [
                    'status' => 'error',
                    'message' => $response->body(),
                ],
            );
        }

        $status = $response->status();
        $transient = in_array($status, [408, 425, 429, 500, 502, 503, 504], true);
        $message = (string) ($response->json('message') ?? $response->body() ?: 'Snippe API request failed.');

        throw new SnippeApiException(
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
            'status' => 'error',
            'code' => $response->status(),
            'message' => $response->body() ?: 'Snippe API request failed.',
        ];
    }
}
