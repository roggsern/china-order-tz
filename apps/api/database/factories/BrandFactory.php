<?php

namespace Database\Factories;

use App\Models\Brand;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Brand>
 */
class BrandFactory extends Factory
{
    protected $model = Brand::class;

    public function definition(): array
    {
        $name = fake()->unique()->company();

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'description' => fake()->paragraph(),
            'logo' => null,
            'banner' => null,
            'website' => fake()->url(),
            'country' => fake()->countryCode(),
            'is_featured' => false,
            'sort_order' => 0,
            'is_active' => true,
        ];
    }

    public function featured(): static
    {
        return $this->state(fn () => ['is_featured' => true]);
    }
}
