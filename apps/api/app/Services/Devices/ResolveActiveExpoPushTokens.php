<?php

namespace App\Services\Devices;

use App\Enums\PushTokenProvider;
use App\Models\DevicePushToken;
use Illuminate\Support\Collection;

/**
 * Resolves active Expo push tokens for a customer (Wave 6B).
 * Ownership always comes from authenticated registration rows — never from payload user_id.
 */
class ResolveActiveExpoPushTokens
{
    /**
     * @return Collection<int, DevicePushToken>
     */
    public function forUserId(string $userId): Collection
    {
        $rows = DevicePushToken::query()
            ->where('user_id', $userId)
            ->where('provider', PushTokenProvider::Expo->value)
            ->where('is_active', true)
            ->whereNull('revoked_at')
            ->orderBy('created_at')
            ->get();

        // Defensive dedupe by push_token (keep earliest row).
        return $rows
            ->unique(static fn (DevicePushToken $token): string => $token->push_token)
            ->values();
    }
}
