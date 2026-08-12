<?php

namespace App\Actions\Devices;

use App\Models\DevicePushToken;
use App\Models\User;
use App\Services\Devices\DevicePushTokenService;

class RegisterDevicePushTokenAction
{
    public function __construct(
        private readonly DevicePushTokenService $devicePushTokens,
    ) {}

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
    public function handle(User $user, array $payload): DevicePushToken
    {
        return $this->devicePushTokens->register($user, $payload);
    }
}
