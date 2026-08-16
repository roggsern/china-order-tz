<?php

namespace App\Actions\Devices;

use App\Models\Admin;
use App\Models\DevicePushToken;
use App\Services\Devices\DevicePushTokenService;

class RegisterAdminDevicePushTokenAction
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
    public function handle(Admin $admin, array $payload): DevicePushToken
    {
        return $this->devicePushTokens->registerForAdmin($admin, $payload);
    }
}
