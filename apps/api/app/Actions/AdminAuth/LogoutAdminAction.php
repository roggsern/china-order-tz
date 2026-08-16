<?php

namespace App\Actions\AdminAuth;

use App\Events\Audit\AdminLogout;
use App\Models\Admin;
use App\Services\Devices\DevicePushTokenService;
use Illuminate\Auth\AuthenticationException;

class LogoutAdminAction
{
    public function __construct(
        private readonly DevicePushTokenService $devicePushTokens,
    ) {}

    /**
     * @param  array{installation_id?: string|null, push_token?: string|null}  $deviceHints
     */
    public function handle(array $deviceHints = []): void
    {
        /** @var Admin|null $admin */
        $admin = auth('sanctum')->user();

        if (! $admin instanceof Admin) {
            throw new AuthenticationException('Unauthenticated.');
        }

        $installationId = isset($deviceHints['installation_id']) && is_string($deviceHints['installation_id'])
            ? $deviceHints['installation_id']
            : null;
        $pushToken = isset($deviceHints['push_token']) && is_string($deviceHints['push_token'])
            ? $deviceHints['push_token']
            : null;

        if ($installationId !== null || $pushToken !== null) {
            $this->devicePushTokens->deactivateCurrentForAdmin($admin, $installationId, $pushToken);
        }

        event(AdminLogout::fromAdmin($admin, request()->ip(), request()->userAgent()));

        $admin->currentAccessToken()?->delete();
    }
}
