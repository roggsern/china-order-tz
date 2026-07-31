<?php

namespace Tests\Unit\Settings;

use App\Support\Settings\SettingsSecretGuard;
use Tests\TestCase;

class SettingsSecretGuardTest extends TestCase
{
    public function test_detects_secret_keys_and_masks_values(): void
    {
        $this->assertTrue(SettingsSecretGuard::isSecretKey('payments.nmb_password'));
        $this->assertTrue(SettingsSecretGuard::isSecretKey('webhook_secret'));
        $this->assertTrue(SettingsSecretGuard::isSecretKey('api_token'));
        $this->assertFalse(SettingsSecretGuard::isSecretKey('payments.default_provider'));
        $this->assertFalse(SettingsSecretGuard::isSecretKey('features.maintenance_mode'));
        $this->assertSame('[REDACTED]', SettingsSecretGuard::mask('super-secret'));
    }
}
