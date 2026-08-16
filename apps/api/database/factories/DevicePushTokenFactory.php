<?php

namespace Database\Factories;

use App\Enums\PushTokenPlatform;
use App\Enums\PushTokenProvider;
use App\Models\Admin;
use App\Models\DevicePushToken;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<DevicePushToken>
 */
class DevicePushTokenFactory extends Factory
{
    protected $model = DevicePushToken::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'admin_id' => null,
            'push_token' => 'ExponentPushToken['.Str::random(22).']',
            'provider' => PushTokenProvider::Expo,
            'platform' => PushTokenPlatform::Android,
            'installation_id' => (string) Str::uuid(),
            'app_version' => '0.1.0',
            'device_name' => 'Test Device',
            'is_active' => true,
            'last_seen_at' => now(),
            'revoked_at' => null,
        ];
    }

    public function forAdmin(?Admin $admin = null): static
    {
        return $this->state(fn () => [
            'user_id' => null,
            'admin_id' => $admin?->id ?? Admin::factory(),
        ]);
    }

    public function revoked(): static
    {
        return $this->state(fn () => [
            'is_active' => false,
            'revoked_at' => now(),
        ]);
    }
}
