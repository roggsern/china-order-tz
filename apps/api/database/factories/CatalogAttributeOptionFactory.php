<?php

namespace Database\Factories;

use App\Models\CatalogAttribute;
use App\Models\CatalogAttributeOption;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CatalogAttributeOption>
 */
class CatalogAttributeOptionFactory extends Factory
{
    protected $model = CatalogAttributeOption::class;

    public function definition(): array
    {
        $value = fake()->unique()->word();

        return [
            'catalog_attribute_id' => CatalogAttribute::factory(),
            'value' => ucfirst($value),
            'slug' => Str::slug($value),
            'sort_order' => fake()->numberBetween(0, 100),
        ];
    }
}
