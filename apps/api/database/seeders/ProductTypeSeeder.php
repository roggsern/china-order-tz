<?php

namespace Database\Seeders;

use App\Enums\AttributeType;
use App\Models\AttributeDependency;
use App\Models\AttributeDependencyRule;
use App\Models\ProductAttribute;
use App\Models\ProductAttributeValue;
use App\Models\ProductType;
use App\Models\ProductTypeAttribute;
use Illuminate\Database\Seeder;

/**
 * Seeds Product Types, Attributes, Values, type bindings, and sample dependency rules.
 *
 * All of this is configuration data. Application code must not special-case these types.
 */
class ProductTypeSeeder extends Seeder
{
    public function run(): void
    {
        $catalog = $this->catalog();

        foreach ($catalog as $index => $typeDefinition) {
            $productType = ProductType::query()->updateOrCreate(
                ['slug' => $typeDefinition['slug']],
                [
                    'name' => $typeDefinition['name'],
                    'description' => $typeDefinition['description'],
                    'sku_pattern' => $typeDefinition['sku_pattern'] ?? null,
                    'has_configurations' => $typeDefinition['has_configurations'] ?? true,
                    'allows_price_override' => $typeDefinition['allows_price_override'] ?? true,
                    'allows_moq_pricing' => $typeDefinition['allows_moq_pricing'] ?? true,
                    'sort_order' => $index,
                    'is_active' => true,
                ]
            );

            foreach ($typeDefinition['attributes'] as $attrIndex => $attrDefinition) {
                $attribute = $this->upsertAttribute($attrDefinition);

                foreach ($attrDefinition['values'] ?? [] as $valueIndex => $valueDefinition) {
                    $this->upsertValue($attribute, $valueDefinition, $valueIndex);
                }

                ProductTypeAttribute::query()->updateOrCreate(
                    [
                        'product_type_id' => $productType->id,
                        'product_attribute_id' => $attribute->id,
                    ],
                    [
                        'sort_order' => $attrIndex,
                        'is_required' => $attrDefinition['is_required'] ?? false,
                        'participates_in_configuration' => $attrDefinition['participates_in_configuration'] ?? true,
                    ]
                );
            }

            foreach ($typeDefinition['dependencies'] ?? [] as $dependencyDefinition) {
                $this->upsertDependency($productType, $dependencyDefinition);
            }
        }
    }

    /**
     * Metadata-only catalog. Adding a new Product Type means extending this data
     * (or Admin CRUD in later phases) — not writing new app logic.
     *
     * @return list<array<string, mixed>>
     */
    private function catalog(): array
    {
        return [
            [
                'name' => 'Fashion',
                'slug' => 'fashion',
                'description' => 'Apparel and soft goods with size/color configurations.',
                'sku_pattern' => 'FASH-{ATTR:color}-{ATTR:size}',
                'attributes' => [
                    [
                        'slug' => 'color',
                        'name' => 'Color',
                        'type' => AttributeType::Color,
                        'is_filterable' => true,
                        'is_required' => true,
                        'values' => [
                            ['value' => 'Black', 'slug' => 'black', 'color_code' => '#000000'],
                            ['value' => 'White', 'slug' => 'white', 'color_code' => '#FFFFFF'],
                            ['value' => 'Blue', 'slug' => 'blue', 'color_code' => '#2563EB'],
                            ['value' => 'Red', 'slug' => 'red', 'color_code' => '#DC2626'],
                        ],
                    ],
                    [
                        'slug' => 'size',
                        'name' => 'Size',
                        'type' => AttributeType::Select,
                        'is_filterable' => true,
                        'is_required' => true,
                        'values' => [
                            ['value' => 'S', 'slug' => 's'],
                            ['value' => 'M', 'slug' => 'm'],
                            ['value' => 'L', 'slug' => 'l'],
                            ['value' => 'XL', 'slug' => 'xl'],
                        ],
                    ],
                ],
                'dependencies' => [
                    [
                        'source' => 'size',
                        'target' => 'color',
                        'rules' => [
                            // Sample: XL only in Black/White (metadata demo, not hardcoded logic).
                            'xl' => ['black', 'white'],
                        ],
                    ],
                ],
            ],
            [
                'name' => 'Phones',
                'slug' => 'phones',
                'description' => 'Mobile handsets with color and storage configurations.',
                'sku_pattern' => 'PHONE-{ATTR:color}-{ATTR:storage}',
                'attributes' => [
                    [
                        'slug' => 'color',
                        'name' => 'Color',
                        'type' => AttributeType::Color,
                        'is_filterable' => true,
                        'is_required' => true,
                        'values' => [
                            // Shared `color` attribute keeps Fashion values; Phones demos use Silver/Black/Blue.
                            ['value' => 'Silver', 'slug' => 'silver', 'color_code' => '#C0C0C0'],
                            ['value' => 'Black', 'slug' => 'black', 'color_code' => '#000000'],
                            ['value' => 'Blue', 'slug' => 'blue', 'color_code' => '#2563EB'],
                            ['value' => 'White', 'slug' => 'white', 'color_code' => '#FFFFFF'],
                            ['value' => 'Red', 'slug' => 'red', 'color_code' => '#DC2626'],
                        ],
                    ],
                    [
                        'slug' => 'storage',
                        'name' => 'Storage',
                        'type' => AttributeType::Select,
                        'unit' => 'GB',
                        'is_filterable' => true,
                        'is_required' => true,
                        'values' => [
                            ['value' => '128GB', 'slug' => '128gb'],
                            ['value' => '256GB', 'slug' => '256gb'],
                            ['value' => '512GB', 'slug' => '512gb'],
                            ['value' => '1TB', 'slug' => '1tb'],
                        ],
                    ],
                    [
                        'slug' => 'condition',
                        'name' => 'Condition',
                        'type' => AttributeType::Select,
                        'is_filterable' => true,
                        'is_required' => false,
                        // Not part of Color×Storage sellable matrix (catalog metadata only).
                        'participates_in_configuration' => false,
                        'values' => [
                            ['value' => 'New', 'slug' => 'new'],
                            ['value' => 'Refurbished', 'slug' => 'refurbished'],
                        ],
                    ],
                ],
                'dependencies' => [
                    [
                        'source' => 'color',
                        'target' => 'storage',
                        'rules' => [
                            // Silver: all storage options.
                            'silver' => ['128gb', '256gb', '512gb', '1tb'],
                            // Black: 128 / 256 only.
                            'black' => ['128gb', '256gb'],
                            // Blue: 256 / 512 only.
                            'blue' => ['256gb', '512gb'],
                        ],
                    ],
                ],
            ],
            [
                'name' => 'TVs',
                'slug' => 'tvs',
                'description' => 'Television sets with screen size configurations.',
                'sku_pattern' => 'TV-{ATTR:screen-size}',
                'attributes' => [
                    [
                        'slug' => 'screen-size',
                        'name' => 'Screen Size',
                        'type' => AttributeType::Select,
                        'unit' => 'inch',
                        'is_filterable' => true,
                        'is_required' => true,
                        'values' => [
                            ['value' => '32"', 'slug' => '32'],
                            ['value' => '43"', 'slug' => '43'],
                            ['value' => '55"', 'slug' => '55'],
                            ['value' => '65"', 'slug' => '65'],
                        ],
                    ],
                    [
                        'slug' => 'resolution',
                        'name' => 'Resolution',
                        'type' => AttributeType::Select,
                        'is_filterable' => true,
                        'is_required' => true,
                        'values' => [
                            ['value' => 'HD', 'slug' => 'hd'],
                            ['value' => 'Full HD', 'slug' => 'full-hd'],
                            ['value' => '4K', 'slug' => '4k'],
                        ],
                    ],
                ],
                'dependencies' => [
                    [
                        'source' => 'screen-size',
                        'target' => 'resolution',
                        'rules' => [
                            '32' => ['hd', 'full-hd'],
                            '43' => ['full-hd', '4k'],
                            '55' => ['4k'],
                            '65' => ['4k'],
                        ],
                    ],
                ],
            ],
            [
                'name' => 'Shoes',
                'slug' => 'shoes',
                'description' => 'Footwear with size and color configurations.',
                'sku_pattern' => 'SHOE-{ATTR:shoe-size}-{ATTR:color}',
                'attributes' => [
                    [
                        'slug' => 'shoe-size',
                        'name' => 'Shoe Size',
                        'type' => AttributeType::Select,
                        'is_filterable' => true,
                        'is_required' => true,
                        'values' => [
                            ['value' => '40', 'slug' => '40'],
                            ['value' => '41', 'slug' => '41'],
                            ['value' => '42', 'slug' => '42'],
                            ['value' => '43', 'slug' => '43'],
                            ['value' => '44', 'slug' => '44'],
                        ],
                    ],
                    [
                        'slug' => 'color',
                        'name' => 'Color',
                        'type' => AttributeType::Color,
                        'is_filterable' => true,
                        'is_required' => true,
                        'values' => [
                            ['value' => 'Black', 'slug' => 'black', 'color_code' => '#000000'],
                            ['value' => 'White', 'slug' => 'white', 'color_code' => '#FFFFFF'],
                            ['value' => 'Blue', 'slug' => 'blue', 'color_code' => '#2563EB'],
                            ['value' => 'Red', 'slug' => 'red', 'color_code' => '#DC2626'],
                        ],
                    ],
                ],
            ],
            [
                'name' => 'Furniture',
                'slug' => 'furniture',
                'description' => 'Furniture with material and color options.',
                'sku_pattern' => 'FURN-{ATTR:material}-{ATTR:color}',
                'attributes' => [
                    [
                        'slug' => 'material',
                        'name' => 'Material',
                        'type' => AttributeType::Select,
                        'is_filterable' => true,
                        'is_required' => true,
                        'values' => [
                            ['value' => 'Wood', 'slug' => 'wood'],
                            ['value' => 'Metal', 'slug' => 'metal'],
                            ['value' => 'Fabric', 'slug' => 'fabric'],
                        ],
                    ],
                    [
                        'slug' => 'color',
                        'name' => 'Color',
                        'type' => AttributeType::Color,
                        'is_filterable' => true,
                        'is_required' => true,
                        'values' => [
                            ['value' => 'Black', 'slug' => 'black', 'color_code' => '#000000'],
                            ['value' => 'White', 'slug' => 'white', 'color_code' => '#FFFFFF'],
                            ['value' => 'Blue', 'slug' => 'blue', 'color_code' => '#2563EB'],
                            ['value' => 'Red', 'slug' => 'red', 'color_code' => '#DC2626'],
                        ],
                    ],
                ],
            ],
            [
                'name' => 'Beauty',
                'slug' => 'beauty',
                'description' => 'Beauty and personal care products.',
                'sku_pattern' => 'BEAU-{ATTR:shade}',
                'attributes' => [
                    [
                        'slug' => 'shade',
                        'name' => 'Shade',
                        'type' => AttributeType::Select,
                        'is_filterable' => true,
                        'is_required' => false,
                        'values' => [
                            ['value' => 'Light', 'slug' => 'light'],
                            ['value' => 'Medium', 'slug' => 'medium'],
                            ['value' => 'Deep', 'slug' => 'deep'],
                        ],
                    ],
                    [
                        'slug' => 'volume',
                        'name' => 'Volume',
                        'type' => AttributeType::Select,
                        'unit' => 'ml',
                        'is_filterable' => true,
                        'is_required' => false,
                        'values' => [
                            ['value' => '30ml', 'slug' => '30ml'],
                            ['value' => '50ml', 'slug' => '50ml'],
                            ['value' => '100ml', 'slug' => '100ml'],
                        ],
                    ],
                ],
            ],
            [
                'name' => 'Laptops',
                'slug' => 'laptops',
                'description' => 'Notebook computers with RAM and storage configurations.',
                'sku_pattern' => 'LAP-{ATTR:ram}-{ATTR:storage}',
                'attributes' => [
                    [
                        'slug' => 'ram',
                        'name' => 'RAM',
                        'type' => AttributeType::Select,
                        'unit' => 'GB',
                        'is_filterable' => true,
                        'is_required' => true,
                        'values' => [
                            ['value' => '8GB', 'slug' => '8gb'],
                            ['value' => '16GB', 'slug' => '16gb'],
                            ['value' => '32GB', 'slug' => '32gb'],
                        ],
                    ],
                    [
                        'slug' => 'storage',
                        'name' => 'Storage',
                        'type' => AttributeType::Select,
                        'unit' => 'GB',
                        'is_filterable' => true,
                        'is_required' => true,
                        'values' => [
                            ['value' => '128GB', 'slug' => '128gb'],
                            ['value' => '256GB', 'slug' => '256gb'],
                            ['value' => '512GB', 'slug' => '512gb'],
                        ],
                    ],
                ],
                'dependencies' => [
                    [
                        'source' => 'ram',
                        'target' => 'storage',
                        'rules' => [
                            '8gb' => ['128gb', '256gb'],
                            '16gb' => ['256gb', '512gb'],
                            '32gb' => ['512gb'],
                        ],
                    ],
                ],
            ],
            [
                'name' => 'Accessories',
                'slug' => 'accessories',
                'description' => 'Electronics accessories with color options.',
                'sku_pattern' => 'ACC-{ATTR:color}',
                'attributes' => [
                    [
                        'slug' => 'color',
                        'name' => 'Color',
                        'type' => AttributeType::Color,
                        'is_filterable' => true,
                        'is_required' => false,
                        'values' => [
                            ['value' => 'Black', 'slug' => 'black', 'color_code' => '#000000'],
                            ['value' => 'White', 'slug' => 'white', 'color_code' => '#FFFFFF'],
                            ['value' => 'Blue', 'slug' => 'blue', 'color_code' => '#2563EB'],
                            ['value' => 'Red', 'slug' => 'red', 'color_code' => '#DC2626'],
                        ],
                    ],
                ],
            ],
            [
                'name' => 'Kitchenware',
                'slug' => 'kitchenware',
                'description' => 'Kitchen products with material options.',
                'sku_pattern' => 'KIT-{ATTR:material}',
                'attributes' => [
                    [
                        'slug' => 'material',
                        'name' => 'Material',
                        'type' => AttributeType::Select,
                        'is_filterable' => true,
                        'is_required' => false,
                        'values' => [
                            ['value' => 'Wood', 'slug' => 'wood'],
                            ['value' => 'Metal', 'slug' => 'metal'],
                            ['value' => 'Fabric', 'slug' => 'fabric'],
                        ],
                    ],
                ],
            ],
            [
                'name' => 'Simple Goods',
                'slug' => 'simple-goods',
                'description' => 'Single-SKU products without variant combinations (price + stock only).',
                'sku_pattern' => 'SMP-{SKU}',
                'has_configurations' => false,
                'allows_price_override' => false,
                'allows_moq_pricing' => true,
                'attributes' => [],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $definition
     */
    private function upsertAttribute(array $definition): ProductAttribute
    {
        return ProductAttribute::query()->updateOrCreate(
            ['slug' => $definition['slug']],
            [
                'name' => $definition['name'],
                'type' => $definition['type'],
                'unit' => $definition['unit'] ?? null,
                'validation' => $definition['validation'] ?? null,
                'is_filterable' => $definition['is_filterable'] ?? true,
                'sort_order' => $definition['sort_order'] ?? 0,
            ]
        );
    }

    /**
     * @param  array<string, mixed>  $definition
     */
    private function upsertValue(ProductAttribute $attribute, array $definition, int $index): ProductAttributeValue
    {
        return ProductAttributeValue::query()->updateOrCreate(
            [
                'product_attribute_id' => $attribute->id,
                'slug' => $definition['slug'],
            ],
            [
                'value' => $definition['value'],
                'color_code' => $definition['color_code'] ?? null,
                'sort_order' => $definition['sort_order'] ?? $index,
            ]
        );
    }

    /**
     * @param  array{source: string, target: string, rules: array<string, list<string>>}  $definition
     */
    private function upsertDependency(ProductType $productType, array $definition): void
    {
        $source = ProductAttribute::query()->where('slug', $definition['source'])->firstOrFail();
        $target = ProductAttribute::query()->where('slug', $definition['target'])->firstOrFail();

        $dependency = AttributeDependency::query()->updateOrCreate(
            [
                'product_type_id' => $productType->id,
                'product_id' => null,
                'source_attribute_id' => $source->id,
                'target_attribute_id' => $target->id,
            ],
            []
        );

        $dependency->rules()->delete();

        foreach ($definition['rules'] as $sourceValueSlug => $targetValueSlugs) {
            $sourceValue = ProductAttributeValue::query()
                ->where('product_attribute_id', $source->id)
                ->where('slug', $sourceValueSlug)
                ->firstOrFail();

            foreach ($targetValueSlugs as $targetValueSlug) {
                $targetValue = ProductAttributeValue::query()
                    ->where('product_attribute_id', $target->id)
                    ->where('slug', $targetValueSlug)
                    ->firstOrFail();

                AttributeDependencyRule::query()->create([
                    'attribute_dependency_id' => $dependency->id,
                    'source_attribute_value_id' => $sourceValue->id,
                    'target_attribute_value_id' => $targetValue->id,
                ]);
            }
        }
    }
}
