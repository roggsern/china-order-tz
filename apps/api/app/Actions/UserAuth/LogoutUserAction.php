<?php

namespace App\Actions\UserAuth;

use App\Events\Audit\CustomerLogoutAudit;
use App\Models\User;
use Illuminate\Auth\AuthenticationException;
use Laravel\Sanctum\PersonalAccessToken;

class LogoutUserAction
{
    /**
     * Revoke only the current customer personal access token (not other devices).
     */
    public function handle(): void
    {
        /** @var User|null $user */
        $user = auth('sanctum')->user();

        if (! $user instanceof User) {
            throw new AuthenticationException('Unauthenticated.');
        }

        event(CustomerLogoutAudit::fromUser($user, request()->ip(), request()->userAgent()));

        $accessToken = $user->currentAccessToken()
            ?? PersonalAccessToken::findToken((string) request()->bearerToken());

        $accessToken?->delete();
    }
}
