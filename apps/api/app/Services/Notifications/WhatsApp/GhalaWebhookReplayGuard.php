<?php

namespace App\Services\Notifications\WhatsApp;

use Illuminate\Support\Facades\Cache;

/**
 * Fast-path dedupe for X-Ghala-Delivery (stable across Ghala retries).
 *
 * Cache-backed with a 24-hour TTL. Cache loss is safe: GhalaWebhookProcessor
 * is idempotent for the same logical status event, so a replay after expiry
 * cannot regress provider metadata or mutate order/payment state.
 */
final class GhalaWebhookReplayGuard
{
    public function hasProcessed(string $deliveryId): bool
    {
        if ($deliveryId === '') {
            return false;
        }

        return Cache::has($this->key($deliveryId));
    }

    public function remember(string $deliveryId): void
    {
        if ($deliveryId === '') {
            return;
        }

        Cache::put(
            $this->key($deliveryId),
            true,
            now()->addSeconds($this->ttlSeconds()),
        );
    }

    private function key(string $deliveryId): string
    {
        return 'ghala:webhook:delivery:'.sha1($deliveryId);
    }

    private function ttlSeconds(): int
    {
        return max(3600, (int) config('notifications.whatsapp.webhook_replay_ttl_seconds', 86400));
    }
}
