<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductVariant>
 */
class ProductVariantFactory extends Factory
{
    protected $model = ProductVariant::class;

    public function definition(): array
    {
        $price = fake()->randomFloat(2, 5000, 500000);

        return [
            'product_id' => Product::factory(),
            'sku' => strtoupper(fake()->unique()->bothify('VAR-####-??')),
            'name' => fake()->optional()->words(2, true),
            'price' => $price,
            'compare_at_price' => $price * 1.15,
            'cost_price' => $price * 0.55,
            'barcode' => fake()->optional()->numerify('############'),
            'weight' => fake()->randomFloat(3, 0.1, 5),
            'is_active' => true,
            'is_default' => false,
            'sort_order' => 0,
        ];
    }
}
