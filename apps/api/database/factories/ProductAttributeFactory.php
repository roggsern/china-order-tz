<?php

namespace Database\Factories;

use App\Enums\AttributeType;
use App\Models\ProductAttribute;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ProductAttribute>
 */
class ProductAttributeFactory extends Factory
{
    protected $model = ProductAttribute::class;

    public function definition(): array
    {
        $name = fake()->unique()->word();

        return [
            'name' => ucfirst($name),
            'slug' => Str::slug($name),
            'type' => fake()->randomElement(AttributeType::cases()),
            'is_filterable' => fake()->boolean(70),
            'sort_order' => fake()->numberBetween(0, 20),
        ];
    }
}
