<?php

namespace App\Actions\Devices;

use App\Models\User;
use App\Services\Devices\DevicePushTokenService;

class DeactivateDevicePushTokenAction
{
    public function __construct(
        private readonly DevicePushTokenService $devicePushTokens,
    ) {}

    public function handle(
        User $user,
        ?string $installationId = null,
        ?string $pushToken = null,
    ): int {
        return $this->devicePushTokens->deactivateCurrent(
            $user,
            $installationId,
            $pushToken,
        );
    }
}
