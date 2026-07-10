<?php

namespace App\Payments\Gateways\Nmb;

use Illuminate\Support\Facades\Cache;

class NmbReplayGuard
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function isDuplicate(array $payload): bool
    {
        $key = $this->cacheKey($payload);

        if ($key === null) {
            return false;
        }

        return Cache::has($key);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function remember(array $payload): void
    {
        $key = $this->cacheKey($payload);

        if ($key === null) {
            return;
        }

        Cache::put(
            $key,
            true,
            now()->addSeconds((int) config('services.nmb.webhook.replay_ttl_seconds', 86400)),
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function cacheKey(array $payload): ?string
    {
        $verifier = app(NmbCallbackVerifier::class);
        $sessionId = $verifier->extractSessionId($payload);
        $orderReference = $verifier->extractOrderReference($payload);
        $result = isset($payload['result']) ? (string) $payload['result'] : '';
        $transaction = is_array($payload['transaction'] ?? null)
            ? (string) ($payload['transaction']['id'] ?? '')
            : '';

        if (! filled($sessionId) && ! filled($orderReference)) {
            return null;
        }

        $fingerprint = sha1(json_encode([
            'session_id' => $sessionId,
            'order_reference' => $orderReference,
            'result' => $result,
            'transaction_id' => $transaction,
        ]));

        return "nmb:callback:replay:{$fingerprint}";
    }
}
