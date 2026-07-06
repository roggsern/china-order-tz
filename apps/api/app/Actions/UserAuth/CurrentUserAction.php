<?php

namespace App\Actions\UserAuth;

use App\Models\User;
use Illuminate\Auth\AuthenticationException;

class CurrentUserAction
{
    public function handle(): User
    {
        /** @var User|null $user */
        $user = auth('sanctum')->user();

        if (! $user instanceof User) {
            throw new AuthenticationException('Unauthenticated.');
        }

        return $user->load('roles');
    }
}
