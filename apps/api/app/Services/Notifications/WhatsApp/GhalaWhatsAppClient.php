<?php

namespace App\Services\Notifications\WhatsApp;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Focused Ghala Developer API client for outbound WhatsApp templates.
 */
final class GhalaWhatsAppClient
{
    /**
     * @param  list<string>  $bodyParameters
     * @return array{success: bool, provider_message_id: string|null, wa_message_id: string|null, status: string|null, error: string|null, retryable: bool}
     */
    public function sendTemplate(
        string $to,
        string $templateName,
        string $language,
        array $bodyParameters,
        string $idempotencyKey,
    ): array {
        $payload = [
            'to' => $to,
            'type' => 'template',
            'template_name' => $templateName,
            'template_language' => $language,
        ];

        if ($bodyParameters !== []) {
            $payload['template_components'] = [
                [
                    'type' => 'body',
                    'parameters' => array_map(
                        static fn (string $text): array => [
                            'type' => 'text',
                            'text' => $text,
                        ],
                        $bodyParameters,
                    ),
                ],
            ];
        }

        $maxAttempts = max(1, (int) config('notifications.whatsapp.retry_attempts', 3));
        $delayMs = max(0, (int) config('notifications.whatsapp.retry_sleep_ms', 200));
        $inProgressDelayMs = $this->inProgressSleepMs($delayMs);
        $attempt = 0;
        $lastFailure = $this->failure('Ghala request failed', retryable: true);

        while ($attempt < $maxAttempts) {
            $attempt++;

            try {
                $response = $this->postMessage($payload, $idempotencyKey);
            } catch (ConnectionException $e) {
                $lastFailure = $this->failure(
                    'Ghala connection/timeout: '.$this->sanitize($e->getMessage()),
                    retryable: true,
                );
                Log::warning('notification.whatsapp.connection_failed', [
                    'attempt' => $attempt,
                    'error' => $this->sanitize($e->getMessage()),
                ]);
                if ($attempt < $maxAttempts) {
                    $this->sleep($delayMs, $attempt);
                }

                continue;
            } catch (RequestException $e) {
                $lastFailure = $this->failure(
                    'Ghala request failed: '.$this->sanitize($e->getMessage()),
                    retryable: true,
                );
                Log::warning('notification.whatsapp.request_failed', [
                    'attempt' => $attempt,
                    'error' => $this->sanitize($e->getMessage()),
                ]);
                if ($attempt < $maxAttempts) {
                    $this->sleep($delayMs, $attempt);
                }

                continue;
            } catch (Throwable $e) {
                Log::warning('notification.whatsapp.send_failed', [
                    'attempt' => $attempt,
                    'error' => $this->sanitize($e->getMessage()),
                ]);

                return $this->failure($this->sanitize($e->getMessage()), retryable: false);
            }

            if ($response->successful()) {
                return $this->successFrom($response);
            }

            $classified = $this->classifyError($response->status(), $response->json());
            $lastFailure = $classified;

            if ($this->isIdempotencyInProgress($response->status(), $response->json())) {
                return $this->retryIdempotencyInProgress(
                    $payload,
                    $idempotencyKey,
                    $inProgressDelayMs,
                );
            }

            if (! $classified['retryable'] || $attempt >= $maxAttempts) {
                return $classified;
            }

            $this->sleep($delayMs, $attempt);
        }

        return $lastFailure;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function postMessage(array $payload, string $idempotencyKey): Response
    {
        $timeout = max(1, (int) config('notifications.whatsapp.timeout', 10));
        $connectTimeout = max(1, (int) config('notifications.whatsapp.connect_timeout', 5));

        return Http::withToken((string) config('notifications.whatsapp.access_token'))
            ->acceptJson()
            ->asJson()
            ->withHeaders([
                'Idempotency-Key' => $idempotencyKey,
            ])
            ->timeout($timeout)
            ->connectTimeout($connectTimeout)
            ->post($this->messagesUrl(), $payload);
    }

    /**
     * @param  array<string, mixed>|mixed  $json
     * @return array{success: bool, provider_message_id: string|null, wa_message_id: string|null, status: string|null, error: string|null, retryable: bool}
     */
    private function classifyError(int $httpStatus, mixed $json): array
    {
        $code = is_array($json) ? (string) ($json['code'] ?? '') : '';
        $message = is_array($json) && is_string($json['message'] ?? null)
            ? (string) $json['message']
            : 'Ghala API error';

        $retryable = $httpStatus >= 500
            || ($httpStatus === 409 && $code === 'idempotency_in_progress');

        $label = match ($code) {
            'not_authenticated' => 'Ghala HTTP 401 (not_authenticated)',
            'plan_feature_locked' => 'Ghala HTTP 402 (plan_feature_locked)',
            'idempotency_in_progress' => 'Ghala HTTP 409 (idempotency_in_progress)',
            'idempotency_key_reused' => 'Ghala HTTP 422 (idempotency_key_reused)',
            default => sprintf(
                'Ghala HTTP %d%s: %s',
                $httpStatus,
                $code !== '' ? ' ('.$code.')' : '',
                $message,
            ),
        };

        return $this->failure($label, retryable: $retryable);
    }

    /**
     * One delayed same-key poll after 409 idempotency_in_progress.
     * Does not enter the 5xx exponential loop and never mints a new key.
     *
     * @param  array<string, mixed>  $payload
     * @return array{success: bool, provider_message_id: string|null, wa_message_id: string|null, status: string|null, error: string|null, retryable: bool}
     */
    private function retryIdempotencyInProgress(
        array $payload,
        string $idempotencyKey,
        int $inProgressDelayMs,
    ): array {
        Log::info('notification.whatsapp.idempotency_in_progress', [
            'retry' => 'single_delayed_same_key',
        ]);
        $this->sleep($inProgressDelayMs, 1);

        try {
            $response = $this->postMessage($payload, $idempotencyKey);
        } catch (ConnectionException $e) {
            return $this->failure(
                'Ghala connection/timeout: '.$this->sanitize($e->getMessage()),
                retryable: true,
            );
        } catch (RequestException $e) {
            return $this->failure(
                'Ghala request failed: '.$this->sanitize($e->getMessage()),
                retryable: true,
            );
        } catch (Throwable $e) {
            return $this->failure($this->sanitize($e->getMessage()), retryable: false);
        }

        if ($response->successful()) {
            return $this->successFrom($response);
        }

        return $this->classifyError($response->status(), $response->json());
    }

    /**
     * @return array{success: bool, provider_message_id: string|null, wa_message_id: string|null, status: string|null, error: string|null, retryable: bool}
     */
    private function successFrom(Response $response): array
    {
        $id = data_get($response->json(), 'id');
        $waId = data_get($response->json(), 'wa_message_id');
        $status = data_get($response->json(), 'status');

        if (! is_string($id) || $id === '') {
            return $this->failure('Ghala response missing message id', retryable: false);
        }

        return [
            'success' => true,
            'provider_message_id' => $id,
            'wa_message_id' => is_string($waId) && $waId !== '' ? $waId : null,
            'status' => is_string($status) && $status !== '' ? $status : 'sent',
            'error' => null,
            'retryable' => false,
        ];
    }

    /**
     * 409 idempotency_in_progress is an in-flight request with the same key.
     * It is not a 5xx: retry at most once, after a longer fixed wait, same key.
     *
     * @param  array<string, mixed>|mixed  $json
     */
    private function isIdempotencyInProgress(int $httpStatus, mixed $json): bool
    {
        $code = is_array($json) ? (string) ($json['code'] ?? '') : '';

        return $httpStatus === 409 && $code === 'idempotency_in_progress';
    }

    private function inProgressSleepMs(int $baseDelayMs): int
    {
        if ($baseDelayMs <= 0) {
            return 0;
        }

        return max(1000, $baseDelayMs * 4);
    }

    public function messagesUrl(): string
    {
        $base = rtrim((string) config('notifications.whatsapp.base_url', 'https://v2.ghala.io'), '/');

        return $base.'/api/v2/messages';
    }

    /**
     * @return array{success: bool, provider_message_id: string|null, wa_message_id: string|null, status: string|null, error: string|null, retryable: bool}
     */
    private function failure(string $error, bool $retryable): array
    {
        return [
            'success' => false,
            'provider_message_id' => null,
            'wa_message_id' => null,
            'status' => null,
            'error' => $this->sanitize($error),
            'retryable' => $retryable,
        ];
    }

    private function sleep(int $baseDelayMs, int $attempt): void
    {
        if ($baseDelayMs <= 0) {
            return;
        }

        $ms = $baseDelayMs * (2 ** max(0, $attempt - 1));
        usleep($ms * 1000);
    }

    private function sanitize(string $message): string
    {
        $token = (string) config('notifications.whatsapp.access_token', '');
        if ($token !== '') {
            $message = str_replace($token, '[redacted]', $message);
        }

        $secret = (string) config('notifications.whatsapp.webhook_secret', '');
        if ($secret !== '') {
            $message = str_replace($secret, '[redacted]', $message);
        }

        $message = preg_replace('/Bearer\s+\S+/i', 'Bearer [redacted]', $message) ?? $message;

        return Str::limit($message, 480, '…');
    }
}
