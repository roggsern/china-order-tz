<?php

namespace App\Actions\Devices;

use App\Models\Admin;
use App\Services\Devices\DevicePushTokenService;

class DeactivateAdminDevicePushTokenAction
{
    public function __construct(
        private readonly DevicePushTokenService $devicePushTokens,
    ) {}

    public function handle(
        Admin $admin,
        ?string $installationId = null,
        ?string $pushToken = null,
    ): int {
        return $this->devicePushTokens->deactivateCurrentForAdmin(
            $admin,
            $installationId,
            $pushToken,
        );
    }
}
