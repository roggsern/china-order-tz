<?php

namespace App\Payments\Gateways\Nmb;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

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
        $attempts = $retry ? max(1, (int) config('services.nmb.http.retry_times', 2) + 1) : 1;
        $lastException = null;

        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            try {
                $pendingRequest = Http::timeout((int) config('services.nmb.http.timeout', 30))
                    ->connectTimeout((int) config('services.nmb.http.connect_timeout', 10))
                    ->acceptJson();

                $pendingRequest = $configureAuth($pendingRequest);

                $response = $method === 'post'
                    ? $pendingRequest->asJson()->post($url, $payload ?? [])
                    : $pendingRequest->get($url);

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
        if ($response->successful()) {
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

        $status = $response->status();
        $transient = in_array($status, [408, 425, 429, 500, 502, 503, 504], true);
        $message = (string) ($response->json('error.explanation') ?? $response->body() ?: 'NMB API request failed.');

        throw new NmbApiException(
            message: $message,
            transient: $transient,
            statusCode: $status,
        );
    }
}
