<?php

namespace Tests\Unit\Features;

use App\Services\Features\FeatureFlagResolver;
use App\Services\Settings\SettingsService;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class FeatureFlagResolverTest extends TestCase
{
    use RefreshDatabase;

    private FeatureFlagResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SettingsSeeder::class);
        Cache::flush();
        $this->resolver = app(FeatureFlagResolver::class);
    }

    public function test_defaults_are_disabled(): void
    {
        $this->assertSame([
            'wishlist' => false,
            'reviews' => false,
            'new_checkout' => false,
        ], $this->resolver->resolveFlags());
        $this->assertSame([], $this->resolver->enabledFeatures());
    }

    public function test_forbidden_flag_detection(): void
    {
        $this->assertTrue($this->resolver->isForbiddenFlag('payment_verification'));
        $this->assertTrue($this->resolver->isForbiddenFlag('inventory_reservation'));
        $this->assertTrue($this->resolver->isForbiddenFlag('order_lifecycle'));
        $this->assertTrue($this->resolver->isForbiddenFlag('permissions_admin'));
        $this->assertFalse($this->resolver->isForbiddenFlag('wishlist'));
        $this->assertFalse($this->resolver->isAllowedFlag('payment_verification'));
    }

    public function test_reads_updated_flags_from_settings(): void
    {
        app(SettingsService::class)->set('features.flags', [
            'wishlist' => true,
            'reviews' => true,
            'new_checkout' => false,
        ]);
        Cache::flush();

        $this->assertTrue($this->resolver->isEnabled('wishlist'));
        $this->assertTrue($this->resolver->isEnabled('reviews'));
        $this->assertFalse($this->resolver->isEnabled('new_checkout'));
        $this->assertSame(['wishlist', 'reviews'], $this->resolver->enabledFeatures());
    }
}
