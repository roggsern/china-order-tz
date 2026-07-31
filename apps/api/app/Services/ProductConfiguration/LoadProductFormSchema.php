<?php

namespace App\Services\ProductConfiguration;

use App\Models\CatalogProductType;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductType;
use App\Models\ProductVariant;
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
        if ($this->usesCatalogVariantConfiguration($product)) {
            return $this->buildFromCatalogProductType($product);
        }

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

    public function usesCatalogVariantConfiguration(Product $product): bool
    {
        if (! filled($product->catalog_product_type_id)) {
            return false;
        }

        return ProductVariant::query()
            ->where('product_id', $product->id)
            ->where('is_active', true)
            ->whereHas('catalogAttributeValues')
            ->exists();
    }

    /**
     * @return array{
     *     product_type: CatalogProductType|null,
     *     attributes: Collection<int, array<string, mixed>>,
     *     dependencies: list<array<string, mixed>>,
     *     capabilities: array{has_configurations: bool, allows_price_override: bool, allows_moq_pricing: bool}
     * }
     */
    private function buildFromCatalogProductType(Product $product): array
    {
        $product->loadMissing(['catalogProductType.attributes.options']);

        $catalogType = $product->catalogProductType;

        if ($catalogType === null) {
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

        $configurationAttributeIds = $this->resolveCatalogConfigurationAttributeIds($product);

        $attributes = $catalogType->attributes
            ->sortBy(fn ($attribute) => $attribute->pivot->sort_order ?? 0)
            ->values()
            ->map(function ($attribute) use ($configurationAttributeIds) {
                return [
                    'id' => $attribute->id,
                    'name' => $attribute->name,
                    'slug' => $attribute->slug,
                    'type' => $attribute->type?->value ?? $attribute->type,
                    'unit' => $attribute->unit,
                    'validation' => null,
                    'is_filterable' => (bool) $attribute->is_filterable,
                    'sort_order' => (int) ($attribute->pivot->sort_order ?? 0),
                    'is_required' => (bool) ($attribute->pivot->is_required ?? $attribute->is_required),
                    'participates_in_configuration' => $configurationAttributeIds->contains($attribute->id),
                    'values' => $attribute->options
                        ->sortBy('sort_order')
                        ->values()
                        ->map(fn ($option) => [
                            'id' => $option->id,
                            'value' => $option->value,
                            'slug' => $option->slug,
                            'color_code' => null,
                            'sort_order' => $option->sort_order,
                        ])
                        ->all(),
                ];
            });

        return [
            'product_type' => $catalogType,
            'attributes' => $attributes,
            'dependencies' => [],
            'capabilities' => [
                'has_configurations' => true,
                'allows_price_override' => false,
                'allows_moq_pricing' => false,
            ],
        ];
    }

    /**
     * Catalog variants may carry fixed descriptive attributes (brand, material, RAM)
     * alongside selectable axes (color, storage). Only attributes with more than
     * one distinct option across active variants participate in storefront matching.
     *
     * @return Collection<int, string>
     */
    private function resolveCatalogConfigurationAttributeIds(Product $product): Collection
    {
        $variants = ProductVariant::query()
            ->where('product_id', $product->id)
            ->where('is_active', true)
            ->with('catalogAttributeValues')
            ->get();

        if ($variants->isEmpty()) {
            return collect();
        }

        /** @var array<string, array<string, true>> $distinctValuesByAttribute */
        $distinctValuesByAttribute = [];

        foreach ($variants as $variant) {
            foreach ($variant->catalogAttributeValues as $row) {
                $signature = $row->option_id ?? $row->value_text;
                if (! filled($signature)) {
                    continue;
                }

                $distinctValuesByAttribute[$row->catalog_attribute_id][$signature] = true;
            }
        }

        return collect($distinctValuesByAttribute)
            ->filter(fn (array $signatures) => count($signatures) > 1)
            ->keys()
            ->values();
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
