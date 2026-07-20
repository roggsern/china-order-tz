<?php

namespace Database\Factories;

use App\Enums\CatalogAttributeType;
use App\Models\CatalogAttribute;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CatalogAttribute>
 */
class CatalogAttributeFactory extends Factory
{
    protected $model = CatalogAttribute::class;

    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'name' => ucwords($name),
            'slug' => Str::slug($name),
            'type' => CatalogAttributeType::Select,
            'unit' => null,
            'is_filterable' => true,
            'is_required' => false,
            'sort_order' => fake()->numberBetween(0, 100),
            'is_active' => true,
        ];
    }
}
