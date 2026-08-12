<?php

namespace App\Services\Devices;

use App\Enums\PushTokenPlatform;
use App\Enums\PushTokenProvider;
use App\Models\DevicePushToken;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Canonical ownership for mobile push tokens (Wave 6A).
 * Does not send push — registration / detach only.
 */
class DevicePushTokenService
{
    /**
     * @param  array{
     *   push_token: string,
     *   provider: string,
     *   platform: string,
     *   installation_id: string,
     *   app_version?: string|null,
     *   device_name?: string|null
     * }  $payload
     */
    public function register(User $user, array $payload): DevicePushToken
    {
        $pushToken = trim((string) $payload['push_token']);
        $installationId = strtolower(trim((string) $payload['installation_id']));
        $provider = PushTokenProvider::from((string) $payload['provider']);
        $platform = PushTokenPlatform::from((string) $payload['platform']);
        $appVersion = isset($payload['app_version']) && is_string($payload['app_version'])
            ? trim($payload['app_version'])
            : null;
        $deviceName = isset($payload['device_name']) && is_string($payload['device_name'])
            ? trim($payload['device_name'])
            : null;

        if ($pushToken === '' || $installationId === '') {
            throw new InvalidArgumentException('push_token and installation_id are required.');
        }

        return DB::transaction(function () use (
            $user,
            $pushToken,
            $installationId,
            $provider,
            $platform,
            $appVersion,
            $deviceName,
        ): DevicePushToken {
            $byToken = DevicePushToken::query()
                ->where('push_token', $pushToken)
                ->lockForUpdate()
                ->first();

            $byInstallation = DevicePushToken::query()
                ->where('installation_id', $installationId)
                ->lockForUpdate()
                ->first();

            if (
                $byToken !== null
                && $byInstallation !== null
                && $byToken->id !== $byInstallation->id
            ) {
                // Same phone rotated onto a token already stored on another row:
                // keep the token row as canonical and retire the stale installation row.
                $byInstallation->delete();
                $byInstallation = null;
            }

            $attributes = [
                'user_id' => $user->id,
                'push_token' => $pushToken,
                'provider' => $provider,
                'platform' => $platform,
                'installation_id' => $installationId,
                'app_version' => $appVersion !== '' ? $appVersion : null,
                'device_name' => $deviceName !== '' ? $deviceName : null,
                'is_active' => true,
                'revoked_at' => null,
                'last_seen_at' => now(),
            ];

            if ($byToken !== null) {
                $byToken->fill($attributes)->save();

                return $byToken->fresh() ?? $byToken;
            }

            if ($byInstallation !== null) {
                $byInstallation->fill($attributes)->save();

                return $byInstallation->fresh() ?? $byInstallation;
            }

            return DevicePushToken::query()->create($attributes);
        });
    }

    /**
     * Deactivate the current installation and/or token for this user only.
     *
     * @return int Number of rows deactivated
     */
    public function deactivateCurrent(
        User $user,
        ?string $installationId = null,
        ?string $pushToken = null,
    ): int {
        $installationId = $installationId !== null ? strtolower(trim($installationId)) : null;
        $pushToken = $pushToken !== null ? trim($pushToken) : null;

        if (($installationId === null || $installationId === '')
            && ($pushToken === null || $pushToken === '')) {
            return 0;
        }

        return DB::transaction(function () use ($user, $installationId, $pushToken): int {
            $query = DevicePushToken::query()
                ->where('user_id', $user->id)
                ->where('is_active', true)
                ->lockForUpdate();

            $query->where(function ($q) use ($installationId, $pushToken): void {
                if ($installationId !== null && $installationId !== '') {
                    $q->orWhere('installation_id', $installationId);
                }
                if ($pushToken !== null && $pushToken !== '') {
                    $q->orWhere('push_token', $pushToken);
                }
            });

            $tokens = $query->get();
            foreach ($tokens as $token) {
                $token->markRevoked();
            }

            return $tokens->count();
        });
    }

    /**
     * Revoke every active token for a customer (account disable / delete / password change).
     */
    public function deactivateAllForUser(User $user): int
    {
        return DB::transaction(function () use ($user): int {
            $tokens = DevicePushToken::query()
                ->where('user_id', $user->id)
                ->where('is_active', true)
                ->lockForUpdate()
                ->get();

            foreach ($tokens as $token) {
                $token->markRevoked();
            }

            return $tokens->count();
        });
    }
}
