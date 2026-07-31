<?php

namespace Tests\Unit\Features;

use App\Services\Features\MaintenanceModeResolver;
use App\Services\Settings\SettingsService;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class MaintenanceModeResolverTest extends TestCase
{
    use RefreshDatabase;

    private MaintenanceModeResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SettingsSeeder::class);
        Cache::flush();
        $this->resolver = app(MaintenanceModeResolver::class);
    }

    public function test_defaults_to_disabled_with_empty_message(): void
    {
        $this->assertFalse($this->resolver->isEnabled());
        $this->assertSame('', $this->resolver->message());
        $this->assertSame(['enabled' => false, 'message' => ''], $this->resolver->status());
    }

    public function test_reads_maintenance_settings(): void
    {
        $settings = app(SettingsService::class);
        $settings->set('features.maintenance_mode', true);
        $settings->set('features.maintenance_message', ' Scheduled downtime ');
        Cache::flush();

        $this->assertTrue($this->resolver->isEnabled());
        $this->assertSame('Scheduled downtime', $this->resolver->message());
        $this->assertSame('Scheduled downtime', $this->resolver->publicMessage());
        $this->assertSame([
            'maintenance' => true,
            'message' => 'Scheduled downtime',
        ], $this->resolver->publicStatus());
        $this->assertSame('maintenance_mode', $this->resolver->blockedResponsePayload()['code']);
    }

    public function test_public_message_falls_back_when_empty(): void
    {
        $this->assertSame(
            'The store is temporarily unavailable for maintenance. Please try again shortly.',
            $this->resolver->publicMessage(),
        );
        $this->assertSame([
            'maintenance' => false,
            'message' => null,
        ], $this->resolver->publicStatus());
    }
}
