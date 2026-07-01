<?php

namespace App\Actions\AdminAuth;

use App\Models\Admin;
use Illuminate\Auth\AuthenticationException;

class CurrentAdminAction
{
    public function handle(): Admin
    {
        /** @var Admin|null $admin */
        $admin = auth('sanctum')->user();

        if (! $admin instanceof Admin) {
            throw new AuthenticationException('Unauthenticated.');
        }

        return $admin->load('role');
    }
}
