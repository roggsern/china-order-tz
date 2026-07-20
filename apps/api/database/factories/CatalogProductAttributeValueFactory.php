<?php

namespace Database\Factories;

use App\Models\CatalogAttribute;
use App\Models\CatalogProductAttributeValue;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CatalogProductAttributeValue>
 */
class CatalogProductAttributeValueFactory extends Factory
{
    protected $model = CatalogProductAttributeValue::class;

    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'catalog_attribute_id' => CatalogAttribute::factory(),
            'value_text' => fake()->words(2, true),
            'value_number' => null,
            'value_boolean' => null,
            'option_id' => null,
            'is_active' => true,
        ];
    }
}
