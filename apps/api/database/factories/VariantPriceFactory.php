<?php

namespace Database\Factories;

use App\Enums\VariantPriceType;
use App\Models\ProductVariant;
use App\Models\VariantPrice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VariantPrice>
 */
class VariantPriceFactory extends Factory
{
    protected $model = VariantPrice::class;

    public function definition(): array
    {
        $amount = fake()->randomFloat(2, 10000, 2500000);

        return [
            'product_variant_id' => ProductVariant::factory(),
            'price_type' => VariantPriceType::Retail,
            'currency' => fake()->randomElement(['TZS', 'USD']),
            'amount' => $amount,
            'compare_at_price' => $amount * 1.15,
            'cost_price' => $amount * 0.6,
            'minimum_quantity' => 1,
            'is_active' => true,
            'starts_at' => null,
            'ends_at' => null,
        ];
    }

    public function retail(): static
    {
        return $this->state(fn () => ['price_type' => VariantPriceType::Retail]);
    }

    public function wholesale(): static
    {
        return $this->state(fn () => [
            'price_type' => VariantPriceType::Wholesale,
            'minimum_quantity' => 5,
        ]);
    }

    public function currency(string $currency): static
    {
        return $this->state(fn () => ['currency' => strtoupper($currency)]);
    }
}
