<?php

namespace Database\Factories;

use App\Models\CatalogProductType;
use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CatalogProductType>
 */
class CatalogProductTypeFactory extends Factory
{
    protected $model = CatalogProductType::class;

    public function definition(): array
    {
        $name = fake()->unique()->words(3, true);

        return [
            'subcategory_id' => Category::factory(),
            'name' => ucwords($name),
            'slug' => Str::slug($name),
            'image' => null,
            'description' => fake()->sentence(),
            'sort_order' => fake()->numberBetween(0, 100),
            'is_active' => true,
        ];
    }
}
