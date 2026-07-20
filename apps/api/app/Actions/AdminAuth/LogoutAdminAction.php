<?php

namespace App\Actions\AdminAuth;

use App\Events\Audit\AdminLogout;
use App\Models\Admin;
use Illuminate\Auth\AuthenticationException;

class LogoutAdminAction
{
    public function handle(): void
    {
        /** @var Admin|null $admin */
        $admin = auth('sanctum')->user();

        if (! $admin instanceof Admin) {
            throw new AuthenticationException('Unauthenticated.');
        }

        event(AdminLogout::fromAdmin($admin, request()->ip(), request()->userAgent()));

        $admin->currentAccessToken()?->delete();
    }
}
