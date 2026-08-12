<?php

namespace App\Actions\UserAuth;

use App\Events\Audit\CustomerLogoutAudit;
use App\Models\User;
use App\Services\Devices\DevicePushTokenService;
use Illuminate\Auth\AuthenticationException;
use Laravel\Sanctum\PersonalAccessToken;

class LogoutUserAction
{
    public function __construct(
        private readonly DevicePushTokenService $devicePushTokens,
    ) {}

    /**
     * Revoke only the current customer personal access token (not other devices).
     * Optionally detach the current installation's push token when identifiers are provided.
     *
     * @param  array{installation_id?: string|null, push_token?: string|null}  $deviceHints
     */
    public function handle(array $deviceHints = []): void
    {
        /** @var User|null $user */
        $user = auth('sanctum')->user();

        if (! $user instanceof User) {
            throw new AuthenticationException('Unauthenticated.');
        }

        $installationId = isset($deviceHints['installation_id']) && is_string($deviceHints['installation_id'])
            ? $deviceHints['installation_id']
            : null;
        $pushToken = isset($deviceHints['push_token']) && is_string($deviceHints['push_token'])
            ? $deviceHints['push_token']
            : null;

        if ($installationId !== null || $pushToken !== null) {
            $this->devicePushTokens->deactivateCurrent($user, $installationId, $pushToken);
        }

        event(CustomerLogoutAudit::fromUser($user, request()->ip(), request()->userAgent()));

        $accessToken = $user->currentAccessToken()
            ?? PersonalAccessToken::findToken((string) request()->bearerToken());

        $accessToken?->delete();
    }
}
