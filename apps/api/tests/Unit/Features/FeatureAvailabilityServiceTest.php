<?php

namespace Tests\Unit\Features;

use App\Services\Features\FeatureAvailabilityService;
use App\Services\Features\FeatureFlagResolver;
use App\Services\Settings\SettingsService;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class FeatureAvailabilityServiceTest extends TestCase
{
    use RefreshDatabase;

    private FeatureAvailabilityService $availability;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SettingsSeeder::class);
        Cache::flush();
        $this->availability = app(FeatureAvailabilityService::class);
    }

    public function test_defaults_are_disabled(): void
    {
        $this->assertFalse($this->availability->canUseWishlist());
        $this->assertFalse($this->availability->canUseReviews());
        $this->assertFalse($this->availability->canUseNewCheckout());
    }

    public function test_reads_flags_from_resolver(): void
    {
        app(SettingsService::class)->set('features.flags', [
            'wishlist' => true,
            'reviews' => false,
            'new_checkout' => true,
        ]);
        Cache::flush();

        $this->assertTrue($this->availability->isEnabled('wishlist'));
        $this->assertFalse($this->availability->isEnabled('reviews'));
        $this->assertTrue($this->availability->canUseNewCheckout());
    }

    public function test_public_flags_expose_only_allowed_runtime_keys(): void
    {
        app(SettingsService::class)->set('features.flags', [
            'wishlist' => true,
            'reviews' => true,
            'new_checkout' => false,
            'payment_verification' => true,
        ]);
        Cache::flush();

        $public = $this->availability->publicFlags();

        $this->assertSame([
            'wishlist' => true,
            'reviews' => true,
            'new_checkout' => false,
        ], $public);
        $this->assertSame(FeatureFlagResolver::ALLOWED_FLAGS, array_keys($public));
    }
}
