<?php

namespace App\Services\Devices;

use App\Enums\PushTokenProvider;
use App\Models\DevicePushToken;
use Illuminate\Support\Collection;

/**
 * Resolves active Expo push tokens for a principal.
 * Ownership always comes from authenticated registration rows — never from payload ids.
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
            ->whereNull('admin_id')
            ->where('provider', PushTokenProvider::Expo->value)
            ->where('is_active', true)
            ->whereNull('revoked_at')
            ->orderBy('created_at')
            ->get();

        return $this->dedupe($rows);
    }

    /**
     * @return Collection<int, DevicePushToken>
     */
    public function forAdminId(string $adminId): Collection
    {
        $rows = DevicePushToken::query()
            ->where('admin_id', $adminId)
            ->whereNull('user_id')
            ->where('provider', PushTokenProvider::Expo->value)
            ->where('is_active', true)
            ->whereNull('revoked_at')
            ->orderBy('created_at')
            ->get();

        return $this->dedupe($rows);
    }

    /**
     * @param  Collection<int, DevicePushToken>  $rows
     * @return Collection<int, DevicePushToken>
     */
    private function dedupe(Collection $rows): Collection
    {
        return $rows
            ->unique(static fn (DevicePushToken $token): string => $token->push_token)
            ->values();
    }
}
