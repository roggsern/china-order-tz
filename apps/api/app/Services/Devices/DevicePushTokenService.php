<?php

namespace App\Services\Devices;

use App\Enums\PushTokenPlatform;
use App\Enums\PushTokenProvider;
use App\Models\DevicePushToken;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Throwable;

/**
 * Canonical ownership for mobile push tokens (Wave 6A).
 * Does not send push — registration / detach only.
 */
class DevicePushTokenService
{
    private const DEADLOCK_RETRIES = 3;

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

        $lastError = null;
        for ($attempt = 0; $attempt <= self::DEADLOCK_RETRIES; $attempt++) {
            try {
                return $this->registerOnce(
                    $user,
                    $pushToken,
                    $installationId,
                    $provider,
                    $platform,
                    $appVersion !== '' ? $appVersion : null,
                    $deviceName !== '' ? $deviceName : null,
                );
            } catch (Throwable $e) {
                $lastError = $e;
                if (! $this->isRetryableConcurrencyError($e) || $attempt === self::DEADLOCK_RETRIES) {
                    throw $e;
                }
                usleep(25_000 * ($attempt + 1));
            }
        }

        throw $lastError ?? new InvalidArgumentException('Unable to register device push token.');
    }

    private function registerOnce(
        User $user,
        string $pushToken,
        string $installationId,
        PushTokenProvider $provider,
        PushTokenPlatform $platform,
        ?string $appVersion,
        ?string $deviceName,
    ): DevicePushToken {
        return DB::transaction(function () use (
            $user,
            $pushToken,
            $installationId,
            $provider,
            $platform,
            $appVersion,
            $deviceName,
        ): DevicePushToken {
            // Lock candidate rows in stable primary-key order to avoid MySQL deadlocks
            // when concurrent requests lock by push_token vs installation_id differently.
            $candidateIds = DevicePushToken::query()
                ->where(function ($q) use ($pushToken, $installationId): void {
                    $q->where('push_token', $pushToken)
                        ->orWhere('installation_id', $installationId);
                })
                ->orderBy('id')
                ->lockForUpdate()
                ->pluck('id')
                ->all();

            $locked = $candidateIds === []
                ? collect()
                : DevicePushToken::query()
                    ->whereIn('id', $candidateIds)
                    ->orderBy('id')
                    ->get()
                    ->keyBy('id');

            $byToken = $locked->first(
                fn (DevicePushToken $row): bool => $row->push_token === $pushToken,
            );
            $byInstallation = $locked->first(
                fn (DevicePushToken $row): bool => $row->installation_id === $installationId,
            );

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
                'app_version' => $appVersion,
                'device_name' => $deviceName,
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

    private function isRetryableConcurrencyError(Throwable $e): bool
    {
        if (! $e instanceof QueryException) {
            return false;
        }

        $message = strtolower($e->getMessage());
        if (str_contains($message, 'deadlock') || str_contains($message, '1213')) {
            return true;
        }

        // Unique race after concurrent create — retry resolves via lock + update path.
        $sqlState = (string) ($e->errorInfo[0] ?? '');
        $driverCode = (int) ($e->errorInfo[1] ?? 0);

        return $sqlState === '23000' || $driverCode === 1062;
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
                ->orderBy('id')
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
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            foreach ($tokens as $token) {
                $token->markRevoked();
            }

            return $tokens->count();
        });
    }
}
