<?php

namespace App\Payments\Gateways\Snippe;

use Illuminate\Support\Facades\Cache;

/**
 * Tracks successfully delivered Snippe webhook events.
 * Events are remembered only after successful processing to allow provider retries
 * on transient verification failures.
 */
class SnippeReplayGuard
{
    public function hasSuccessfulDelivery(string $eventId): bool
    {
        if ($eventId === '') {
            return false;
        }

        return Cache::has($this->successKey($eventId));
    }

    public function rememberSuccessfulDelivery(string $eventId): void
    {
        if ($eventId === '') {
            return;
        }

        Cache::put(
            $this->successKey($eventId),
            true,
            now()->addSeconds($this->ttlSeconds()),
        );
    }

    private function successKey(string $eventId): string
    {
        return 'snippe:webhook:event:'.sha1($eventId).':success';
    }

    private function ttlSeconds(): int
    {
        return max(3600, (int) SnippeConfig::get('webhook_replay_ttl_seconds', 86400));
    }
}
