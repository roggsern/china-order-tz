<?php

namespace Database\Factories;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        $name = fake()->unique()->words(3, true);
        $price = fake()->randomFloat(2, 5000, 500000);

        return [
            'category_id' => Category::factory(),
            'brand_id' => Brand::factory(),
            'supplier_id' => Supplier::factory(),
            'name' => ucwords($name),
            'slug' => Str::slug($name),
            'sku' => strtoupper(Str::random(8)),
            'description' => fake()->paragraphs(3, true),
            'short_description' => fake()->sentence(),
            'price' => $price,
            'compare_at_price' => $price * 1.2,
            'cost_price' => $price * 0.6,
            'weight' => fake()->randomFloat(3, 0.1, 10),
            'dimensions' => fake()->numerify('##x##x## cm'),
            'is_active' => true,
            'is_featured' => fake()->boolean(20),
            'meta_title' => ucwords($name),
            'meta_description' => fake()->sentence(),
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    public function featured(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_featured' => true,
        ]);
    }

    public function fromChina(): static
    {
        return $this->state(fn (array $attributes) => [
            'supplier_id' => Supplier::factory()->china(),
            'air_shipping_price' => fake()->randomFloat(2, 3000, 15000),
            'sea_shipping_price' => fake()->randomFloat(2, 1500, 8000),
        ]);
    }

    public function fromDar(): static
    {
        return $this->state(fn (array $attributes) => [
            'supplier_id' => Supplier::factory(),
            'air_shipping_price' => null,
            'sea_shipping_price' => null,
        ]);
    }
}
