<?php

namespace Database\Factories;

use App\Models\ConfigurationPriceTier;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ConfigurationPriceTier>
 */
class ConfigurationPriceTierFactory extends Factory
{
    protected $model = ConfigurationPriceTier::class;

    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'product_variant_id' => null,
            'min_quantity' => fake()->randomElement([1, 10, 50]),
            'unit_price' => fake()->randomFloat(2, 1000, 500000),
        ];
    }

    public function forConfiguration(ProductVariant $configuration): static
    {
        return $this->state(fn () => [
            'product_id' => $configuration->product_id,
            'product_variant_id' => $configuration->id,
        ]);
    }
}
