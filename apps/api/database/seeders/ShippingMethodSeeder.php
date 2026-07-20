<?php

namespace Database\Seeders;

use App\Models\ShippingMethod;
use App\Models\ShippingRate;
use Illuminate\Database\Seeder;

/**
 * MASTER_SPECIFICATION seeded defaults:
 * air_freight, sea_freight, local_delivery (+ baseline rates).
 */
class ShippingMethodSeeder extends Seeder
{
    public function run(): void
    {
        $methods = [
            [
                'code' => 'air_freight',
                'name' => 'Air Freight',
                'description' => 'Faster China import shipping by air.',
                'fulfillment_source' => 'imported_from_china',
                'is_active' => true,
                'sort_order' => 1,
                'rate' => [
                    'base_cost' => 25000,
                    'cost_per_kg' => 8000,
                    'estimated_delivery_days' => 10,
                ],
            ],
            [
                'code' => 'sea_freight',
                'name' => 'Sea Freight',
                'description' => 'Economy China import shipping by sea.',
                'fulfillment_source' => 'imported_from_china',
                'is_active' => true,
                'sort_order' => 2,
                'rate' => [
                    'base_cost' => 10000,
                    'cost_per_kg' => 2500,
                    'estimated_delivery_days' => 35,
                ],
            ],
            [
                'code' => 'local_delivery',
                'name' => 'Local Delivery',
                'description' => 'Local Tanzania stock delivery (auto-applied).',
                'fulfillment_source' => 'buy_from_tz',
                'is_active' => true,
                'sort_order' => 3,
                'rate' => [
                    'base_cost' => 5000,
                    'cost_per_kg' => null,
                    'estimated_delivery_days' => 2,
                ],
            ],
        ];

        foreach ($methods as $methodData) {
            $rate = $methodData['rate'];
            unset($methodData['rate']);

            $method = ShippingMethod::query()->updateOrCreate(
                ['code' => $methodData['code']],
                $methodData
            );

            ShippingRate::query()->updateOrCreate(
                [
                    'shipping_method_id' => $method->id,
                    'min_weight' => null,
                    'max_weight' => null,
                ],
                [
                    'base_cost' => $rate['base_cost'],
                    'cost_per_kg' => $rate['cost_per_kg'],
                    'estimated_delivery_days' => $rate['estimated_delivery_days'],
                    'currency' => 'TZS',
                    'is_active' => true,
                ]
            );
        }
    }
}
