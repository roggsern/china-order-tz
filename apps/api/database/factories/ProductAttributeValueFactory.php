<?php

namespace Database\Factories;

use App\Models\ProductAttribute;
use App\Models\ProductAttributeValue;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ProductAttributeValue>
 */
class ProductAttributeValueFactory extends Factory
{
    protected $model = ProductAttributeValue::class;

    public function definition(): array
    {
        $value = fake()->unique()->word();

        return [
            'product_attribute_id' => ProductAttribute::factory(),
            'value' => ucfirst($value),
            'slug' => Str::slug($value),
            'color_code' => fake()->optional()->hexColor(),
            'sort_order' => fake()->numberBetween(0, 20),
        ];
    }
}
