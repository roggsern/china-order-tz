<?php

namespace Database\Factories;

use App\Models\ProductVariant;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SupplierProduct>
 */
class SupplierProductFactory extends Factory
{
    protected $model = SupplierProduct::class;

    public function definition(): array
    {
        return [
            'supplier_id' => Supplier::factory(),
            'product_variant_id' => ProductVariant::factory(),
            'supplier_sku' => strtoupper(fake()->bothify('SUP-????-###')),
            'purchase_cost' => fake()->randomFloat(2, 1000, 200000),
            'currency' => 'TZS',
            'lead_time_days' => fake()->numberBetween(3, 45),
            'is_active' => true,
        ];
    }
}
