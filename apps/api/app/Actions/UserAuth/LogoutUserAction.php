<?php

namespace App\Actions\UserAuth;

use App\Models\User;
use Illuminate\Auth\AuthenticationException;
use Laravel\Sanctum\PersonalAccessToken;

class LogoutUserAction
{
    public function handle(): void
    {
        /** @var User|null $user */
        $user = auth('sanctum')->user();

        if (! $user instanceof User) {
            throw new AuthenticationException('Unauthenticated.');
        }

        $accessToken = $user->currentAccessToken()
            ?? PersonalAccessToken::findToken((string) request()->bearerToken());

        $accessToken?->delete();
    }
}
