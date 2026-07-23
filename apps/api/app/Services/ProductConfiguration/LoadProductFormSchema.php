<?php

namespace App\Services\ProductConfiguration;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductType;
use Illuminate\Support\Collection;

/**
 * Builds the metadata-driven product form schema for Admin / storefront / POS.
 * Consumers render fields from this payload — never from hardcoded attribute lists.
 */
class LoadProductFormSchema
{
    public function __construct(
        private readonly ResolveTypeFromCategory $resolveTypeFromCategory,
        private readonly AttributeDependencyResolver $dependencyResolver,
    ) {}

    /**
     * @return array{
     *     product_type: ?ProductType,
     *     attributes: Collection,
     *     dependencies: list<array<string, mixed>>,
     *     capabilities: array{has_configurations: bool, allows_price_override: bool, allows_moq_pricing: bool}
     * }
     */
    public function forCategory(Category $category): array
    {
        $type = $this->resolveTypeFromCategory->handle($category);

        return $this->build($type);
    }

    /**
     * Prefer the product's type snapshot; fall back to category inheritance.
     *
     * @return array{
     *     product_type: ?ProductType,
     *     attributes: Collection,
     *     dependencies: list<array<string, mixed>>,
     *     capabilities: array{has_configurations: bool, allows_price_override: bool, allows_moq_pricing: bool}
     * }
     */
    public function forProduct(Product $product): array
    {
        $product->loadMissing(['productType', 'category']);

        $type = $product->productType;

        // Inactive / soft-deleted snapshots are invalid — fall through to category walk.
        if ($type !== null && ! $type->is_active) {
            $type = null;
        }

        if ($type === null && $product->category !== null) {
            $type = $this->resolveTypeFromCategory->handle($product->category);
        }

        return $this->build($type, $product);
    }

    /**
     * @return array{
     *     product_type: ?ProductType,
     *     attributes: Collection,
     *     dependencies: list<array<string, mixed>>,
     *     capabilities: array{has_configurations: bool, allows_price_override: bool, allows_moq_pricing: bool}
     * }
     */
    private function build(?ProductType $type, ?Product $product = null): array
    {
        if ($type === null) {
            return [
                'product_type' => null,
                'attributes' => collect(),
                'dependencies' => [],
                'capabilities' => [
                    'has_configurations' => false,
                    'allows_price_override' => false,
                    'allows_moq_pricing' => false,
                ],
            ];
        }

        $type->loadMissing([
            'typeAttributes.attribute.values',
        ]);

        $attributes = $type->typeAttributes
            ->sortBy('sort_order')
            ->values()
            ->map(function ($typeAttribute) {
                $attribute = $typeAttribute->attribute;

                return [
                    'id' => $attribute->id,
                    'name' => $attribute->name,
                    'slug' => $attribute->slug,
                    'type' => $attribute->type?->value ?? $attribute->type,
                    'unit' => $attribute->unit,
                    'validation' => $attribute->validation,
                    'is_filterable' => $attribute->is_filterable,
                    'sort_order' => $typeAttribute->sort_order,
                    'is_required' => $typeAttribute->is_required,
                    'participates_in_configuration' => $typeAttribute->participates_in_configuration,
                    'values' => $attribute->values
                        ->sortBy('sort_order')
                        ->values()
                        ->map(fn ($value) => [
                            'id' => $value->id,
                            'value' => $value->value,
                            'slug' => $value->slug,
                            'color_code' => $value->color_code,
                            'sort_order' => $value->sort_order,
                        ])
                        ->all(),
                ];
            });

        return [
            'product_type' => $type,
            'attributes' => $attributes,
            'dependencies' => $this->dependencyResolver->graph($type, $product),
            'capabilities' => [
                'has_configurations' => (bool) $type->has_configurations,
                'allows_price_override' => (bool) $type->allows_price_override,
                'allows_moq_pricing' => (bool) $type->allows_moq_pricing,
            ],
        ];
    }
}
