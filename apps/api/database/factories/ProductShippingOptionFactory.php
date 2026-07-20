<?php

namespace Database\Factories;

use App\Enums\ShippingMethod;
use App\Models\Product;
use App\Models\ProductShippingOption;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductShippingOption>
 */
class ProductShippingOptionFactory extends Factory
{
    protected $model = ProductShippingOption::class;

    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'transport_mode' => fake()->randomElement([ShippingMethod::Air, ShippingMethod::Sea]),
            'price' => fake()->randomFloat(2, 1500, 15000),
            'currency' => 'TZS',
            'is_available' => true,
            'notes' => null,
            'sort_order' => 0,
        ];
    }

    public function air(?float $price = null): static
    {
        return $this->state(fn () => [
            'transport_mode' => ShippingMethod::Air,
            'price' => $price ?? fake()->randomFloat(2, 3000, 15000),
            'sort_order' => 0,
        ]);
    }

    public function sea(?float $price = null): static
    {
        return $this->state(fn () => [
            'transport_mode' => ShippingMethod::Sea,
            'price' => $price ?? fake()->randomFloat(2, 1500, 8000),
            'sort_order' => 1,
        ]);
    }

    public function unavailable(): static
    {
        return $this->state(fn () => [
            'is_available' => false,
        ]);
    }
}
