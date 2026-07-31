<?php

namespace Tests\Unit\Services\Shipping;

use App\Models\ShippingMethod;
use App\Models\ShippingRate;
use App\Services\Shipping\ShippingDurationResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShippingDurationResolverTest extends TestCase
{
    use RefreshDatabase;

    private ShippingDurationResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = app(ShippingDurationResolver::class);
    }

    public function test_resolve_air_from_shipping_rates(): void
    {
        $this->seedMethodWithRate('air_freight', 7, 12, 10);

        $result = $this->resolver->resolveAir();

        $this->assertSame(7, $result['min_days']);
        $this->assertSame(12, $result['max_days']);
        $this->assertSame(10, $result['typical_days']);
        $this->assertSame('shipping_rates', $result['source']);
        $this->assertSame('air_freight', $result['method_code']);
    }

    public function test_resolve_sea_from_shipping_rates(): void
    {
        $this->seedMethodWithRate('sea_freight', 35, 45, 40);

        $result = $this->resolver->resolveSea();

        $this->assertSame(35, $result['min_days']);
        $this->assertSame(45, $result['max_days']);
        $this->assertSame(40, $result['typical_days']);
        $this->assertSame('shipping_rates', $result['source']);
        $this->assertSame('sea_freight', $result['method_code']);
    }

    public function test_resolve_local_from_shipping_rates(): void
    {
        $this->seedMethodWithRate('local_delivery', 1, 5, 2);

        $result = $this->resolver->resolveLocal();

        $this->assertSame(1, $result['min_days']);
        $this->assertSame(5, $result['max_days']);
        $this->assertSame(2, $result['typical_days']);
        $this->assertSame('shipping_rates', $result['source']);
        $this->assertSame('local_delivery', $result['method_code']);
    }

    public function test_fallback_when_method_missing(): void
    {
        $air = $this->resolver->resolveAir();
        $sea = $this->resolver->resolveSea();
        $local = $this->resolver->resolveLocal();

        $this->assertSame('fallback', $air['source']);
        $this->assertSame(7, $air['min_days']);
        $this->assertSame(12, $air['max_days']);

        $this->assertSame('fallback', $sea['source']);
        $this->assertSame(35, $sea['min_days']);
        $this->assertSame(45, $sea['max_days']);

        $this->assertSame('fallback', $local['source']);
        $this->assertSame(1, $local['min_days']);
        $this->assertSame(5, $local['max_days']);
    }

    public function test_fallback_when_rate_has_no_duration_fields(): void
    {
        $method = ShippingMethod::query()->create([
            'code' => 'air_freight',
            'name' => 'Air Freight',
            'fulfillment_source' => 'imported_from_china',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        ShippingRate::query()->create([
            'shipping_method_id' => $method->id,
            'base_cost' => 1000,
            'currency' => 'TZS',
            'is_active' => true,
            'estimated_delivery_days' => null,
            'estimated_min_days' => null,
            'estimated_max_days' => null,
        ]);

        $result = $this->resolver->resolveAir();

        $this->assertSame('fallback', $result['source']);
        $this->assertSame(7, $result['min_days']);
        $this->assertSame(12, $result['max_days']);
        $this->assertSame(10, $result['typical_days']);
    }

    public function test_derives_window_from_typical_only(): void
    {
        $this->seedMethodWithRate('air_freight', null, null, 10);

        $result = $this->resolver->resolveAir();

        $this->assertSame(10, $result['min_days']);
        $this->assertSame(10, $result['max_days']);
        $this->assertSame(10, $result['typical_days']);
        $this->assertSame('shipping_rates', $result['source']);
    }

    public function test_resolve_all_returns_three_modes(): void
    {
        $this->seedMethodWithRate('air_freight', 7, 12, 10);
        $this->seedMethodWithRate('sea_freight', 35, 45, 40);
        $this->seedMethodWithRate('local_delivery', 1, 5, 2);

        $all = $this->resolver->resolveAll();

        $this->assertArrayHasKey('air', $all);
        $this->assertArrayHasKey('sea', $all);
        $this->assertArrayHasKey('local', $all);
        $this->assertSame('air_freight', $all['air']['method_code']);
        $this->assertSame('sea_freight', $all['sea']['method_code']);
        $this->assertSame('local_delivery', $all['local']['method_code']);
    }

    private function seedMethodWithRate(
        string $code,
        ?int $min,
        ?int $max,
        ?int $typical,
    ): void {
        $fulfillment = $code === 'local_delivery' ? 'buy_from_tz' : 'imported_from_china';

        $method = ShippingMethod::query()->create([
            'code' => $code,
            'name' => $code,
            'fulfillment_source' => $fulfillment,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        ShippingRate::query()->create([
            'shipping_method_id' => $method->id,
            'base_cost' => 1000,
            'currency' => 'TZS',
            'is_active' => true,
            'estimated_delivery_days' => $typical,
            'estimated_min_days' => $min,
            'estimated_max_days' => $max,
        ]);
    }
}
