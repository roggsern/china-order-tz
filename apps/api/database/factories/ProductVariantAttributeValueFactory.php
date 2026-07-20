<?php

namespace Database\Factories;

use App\Models\CatalogAttribute;
use App\Models\ProductVariant;
use App\Models\ProductVariantAttributeValue;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductVariantAttributeValue>
 */
class ProductVariantAttributeValueFactory extends Factory
{
    protected $model = ProductVariantAttributeValue::class;

    public function definition(): array
    {
        return [
            'product_variant_id' => ProductVariant::factory(),
            'catalog_attribute_id' => CatalogAttribute::factory(),
            'option_id' => null,
            'value_text' => fake()->optional()->word(),
            'value_number' => null,
            'value_boolean' => null,
        ];
    }
}
