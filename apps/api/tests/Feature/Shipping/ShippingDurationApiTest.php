<?php

namespace Tests\Feature\Shipping;

use App\Models\ShippingMethod;
use App\Models\ShippingRate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShippingDurationApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_durations_endpoint_returns_rate_windows(): void
    {
        $this->seedRate('air_freight', 7, 12, 10, 'imported_from_china');
        $this->seedRate('sea_freight', 35, 45, 40, 'imported_from_china');
        $this->seedRate('local_delivery', 1, 5, 2, 'buy_from_tz');

        $this->getJson('/api/v1/shipping/durations')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.air.min_days', 7)
            ->assertJsonPath('data.air.max_days', 12)
            ->assertJsonPath('data.air.typical_days', 10)
            ->assertJsonPath('data.air.method_code', 'air_freight')
            ->assertJsonPath('data.air.source', 'shipping_rates')
            ->assertJsonPath('data.sea.min_days', 35)
            ->assertJsonPath('data.sea.max_days', 45)
            ->assertJsonPath('data.sea.source', 'shipping_rates')
            ->assertJsonPath('data.local.min_days', 1)
            ->assertJsonPath('data.local.max_days', 5)
            ->assertJsonPath('data.local.source', 'shipping_rates');
    }

    public function test_public_durations_endpoint_falls_back_when_empty(): void
    {
        $this->getJson('/api/v1/shipping/durations')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.air.source', 'fallback')
            ->assertJsonPath('data.air.min_days', 7)
            ->assertJsonPath('data.air.max_days', 12)
            ->assertJsonPath('data.sea.source', 'fallback')
            ->assertJsonPath('data.sea.min_days', 35)
            ->assertJsonPath('data.sea.max_days', 45)
            ->assertJsonPath('data.local.source', 'fallback')
            ->assertJsonPath('data.local.min_days', 1)
            ->assertJsonPath('data.local.max_days', 5);
    }

    private function seedRate(
        string $code,
        int $min,
        int $max,
        int $typical,
        string $fulfillmentSource,
    ): void {
        $method = ShippingMethod::query()->create([
            'code' => $code,
            'name' => $code,
            'fulfillment_source' => $fulfillmentSource,
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
