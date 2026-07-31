<?php

namespace App\Services\ProductConfiguration;

use App\Models\Product;
use App\Models\ProductType;
use App\Models\ProductVariant;
use App\Services\Catalog\ProductVariantAttributeResolver;
use App\Services\Inventory\StockResolver;
use App\Services\Pricing\CommercePricingResolver;
use App\Services\Pricing\DTOs\CommercePricingContext;
use Illuminate\Support\Collection;

/**
 * Storefront configuration matching + cascading options.
 *
 * Options are driven by:
 * 1. Product Type attribute metadata
 * 2. Attribute Dependency Engine (directed rules)
 * 3. Existing sellable configurations (and stock)
 *
 * No Fashion/Phones/TV hardcoding — all from metadata + configuration rows.
 * Stock reads go through StockResolver (ADR 055).
 * Configuration list prices go through CommercePricingResolver (ADR 054).
 */
class ResolveStorefrontConfigurationOptions
{
    public function __construct(
        private readonly AttributeDependencyResolver $dependencyResolver,
        private readonly LoadProductFormSchema $loadProductFormSchema,
        private readonly StockResolver $stockResolver,
        private readonly ProductVariantAttributeResolver $attributeResolver,
        private readonly CommercePricingResolver $pricingResolver,
    ) {}

    /**
     * @param  array<string, string>  $selections  attribute_id => value_id
     * @return array{
     *     schema: array<string, mixed>,
     *     configurations: list<array<string, mixed>>,
     *     allowed_value_ids: array<string, list<string>>,
     *     matched_configuration_id: ?string,
     *     is_complete: bool,
     *     is_in_stock: bool
     * }
     */
    public function handle(Product $product, array $selections = [], bool $inStockOnly = false): array
    {
        $schema = $this->loadProductFormSchema->forProduct($product);
        $configurations = $this->loadConfigurations($product);
        $type = $schema['product_type'];

        $configAttributes = collect($schema['attributes'] ?? [])
            ->filter(fn (array $attr) => ($attr['participates_in_configuration'] ?? false) === true)
            ->values();
        $configurationAttributeIds = $configAttributes->pluck('id')->all();

        $matching = $this->filterMatchingConfigurations(
            $configurations,
            $selections,
            $configurationAttributeIds,
        );

        if ($inStockOnly) {
            $matching = $matching->filter(fn (array $row) => $row['in_stock'])->values();
        }

        $allowedFromConfigs = $this->allowedValuesFromConfigurations($configAttributes, $matching);
        $allowedFromDeps = $type instanceof ProductType
            ? $this->dependencyResolver->allowedValues($type, $selections, $product)
            : [];

        $allowed = [];
        foreach ($configAttributes as $attribute) {
            $attributeId = $attribute['id'];
            $configAllowed = $allowedFromConfigs[$attributeId] ?? [];
            $depAllowed = $allowedFromDeps[$attributeId] ?? null;

            if ($depAllowed === null) {
                $allowed[$attributeId] = array_values($configAllowed);
            } else {
                $allowed[$attributeId] = array_values(array_intersect($configAllowed, $depAllowed));
            }
        }

        $matched = $this->findExactMatch(
            $configurations,
            $selections,
            $configurationAttributeIds,
        );

        return [
            'schema' => $schema,
            'configurations' => $configurations->all(),
            'allowed_value_ids' => $allowed,
            'matched_configuration_id' => $matched['id'] ?? null,
            'is_complete' => $matched !== null,
            'is_in_stock' => (bool) ($matched['in_stock'] ?? false),
        ];
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function loadConfigurations(Product $product): Collection
    {
        $usesCatalogVariants = $this->loadProductFormSchema->usesCatalogVariantConfiguration($product);

        $variants = ProductVariant::query()
            ->where('product_id', $product->id)
            ->where('is_active', true)
            ->with([
                'catalogAttributeValues.attribute',
                'catalogAttributeValues.option',
                'attributeValues.attribute',
                'prices',
                'inventories',
                'inventory',
            ])
            ->orderBy('name')
            ->get();

        return $variants->map(function (ProductVariant $variant) use ($product, $usesCatalogVariants) {
            $stock = max(0, $this->stockResolver->resolveVariantProduct($variant, null, $product)->quantityAvailable);
            $displayAttributes = $this->attributeResolver->resolve($variant);

            if ($usesCatalogVariants) {
                return [
                    'id' => $variant->id,
                    'sku' => $variant->sku,
                    'name' => $variant->name,
                    'price' => $this->resolveConfigurationListPrice($variant),
                    'attribute_value_ids' => $variant->catalogAttributeValues
                        ->pluck('option_id')
                        ->filter()
                        ->values()
                        ->all(),
                    'attribute_values' => $this->mapCatalogAttributeValues($variant),
                    'display_attributes' => $displayAttributes,
                    'stock' => $stock,
                    'in_stock' => $stock > 0,
                ];
            }

            return [
                'id' => $variant->id,
                'sku' => $variant->sku,
                'name' => $variant->name,
                'price' => $this->resolveConfigurationListPrice($variant),
                'attribute_value_ids' => $variant->attributeValues->pluck('id')->values()->all(),
                'attribute_values' => $variant->attributeValues->map(fn ($value) => [
                    'id' => $value->id,
                    'product_attribute_id' => $value->product_attribute_id,
                    'value' => $value->value,
                    'slug' => $value->slug,
                    'color_code' => $value->color_code,
                    'attribute_slug' => $value->attribute?->slug,
                    'attribute_name' => $value->attribute?->name,
                ])->values()->all(),
                'display_attributes' => $displayAttributes,
                'stock' => $stock,
                'in_stock' => $stock > 0,
            ];
        })->values();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function mapCatalogAttributeValues(ProductVariant $variant): array
    {
        return $variant->catalogAttributeValues
            ->map(function ($row) {
                $attribute = $row->relationLoaded('attribute') ? $row->attribute : null;
                $option = $row->relationLoaded('option') ? $row->option : null;

                return [
                    'id' => $row->option_id ?? $row->id,
                    'product_attribute_id' => $row->catalog_attribute_id,
                    'value' => $option?->value ?? $row->value_text,
                    'slug' => $option?->slug,
                    'color_code' => null,
                    'attribute_slug' => $attribute?->slug,
                    'attribute_name' => $attribute?->name,
                ];
            })
            ->values()
            ->all();
    }

    private function resolveConfigurationListPrice(ProductVariant $variant): mixed
    {
        $product = $variant->relationLoaded('product') ? $variant->product : $variant->product()->first();
        $result = $this->pricingResolver->resolveVariantProductPrice(
            $variant,
            new CommercePricingContext(
                currency: 'TZS',
                allowLegacyVariantFallback: true,
            ),
            $product,
        );

        if (! $result->resolved || (float) $result->unitPrice <= 0) {
            return null;
        }

        return $result->unitPrice;
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $configurations
     * @param  array<string, string>  $selections
     * @param  list<string>  $configurationAttributeIds
     * @return Collection<int, array<string, mixed>>
     */
    private function filterMatchingConfigurations(
        Collection $configurations,
        array $selections,
        array $configurationAttributeIds = [],
    ): Collection {
        if ($selections === []) {
            return $configurations;
        }

        $relevantSelections = $configurationAttributeIds === []
            ? $selections
            : array_intersect_key($selections, array_flip($configurationAttributeIds));

        if ($relevantSelections === []) {
            return $configurations;
        }

        return $configurations->filter(function (array $row) use ($relevantSelections, $configurationAttributeIds) {
            $byAttribute = $this->configurationValuesByAttribute($row, $configurationAttributeIds);

            foreach ($relevantSelections as $attributeId => $valueId) {
                if (($byAttribute[$attributeId] ?? null) !== $valueId) {
                    return false;
                }
            }

            return true;
        })->values();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $attributes
     * @param  Collection<int, array<string, mixed>>  $configurations
     * @return array<string, list<string>>
     */
    private function allowedValuesFromConfigurations(Collection $attributes, Collection $configurations): array
    {
        $allowed = [];

        foreach ($attributes as $attribute) {
            $attributeId = $attribute['id'];
            $allowed[$attributeId] = $configurations
                ->flatMap(fn (array $row) => collect($row['attribute_values'])
                    ->where('product_attribute_id', $attributeId)
                    ->pluck('id'))
                ->unique()
                ->values()
                ->all();
        }

        return $allowed;
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  list<string>  $configurationAttributeIds
     * @return Collection<string, string>
     */
    private function configurationValuesByAttribute(array $row, array $configurationAttributeIds): Collection
    {
        return collect($row['attribute_values'])
            ->when(
                $configurationAttributeIds !== [],
                fn (Collection $values) => $values->whereIn('product_attribute_id', $configurationAttributeIds),
            )
            ->mapWithKeys(fn (array $value) => [
                $value['product_attribute_id'] => $value['id'],
            ]);
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $configurations
     * @param  array<string, string>  $selections
     * @param  list<string>  $configurationAttributeIds
     * @return array<string, mixed>|null
     */
    private function findExactMatch(
        Collection $configurations,
        array $selections,
        array $configurationAttributeIds,
    ): ?array {
        if ($configurationAttributeIds === []) {
            return null;
        }

        foreach ($configurationAttributeIds as $attributeId) {
            if (! filled($selections[$attributeId] ?? null)) {
                return null;
            }
        }

        return $configurations->first(function (array $row) use ($selections, $configurationAttributeIds) {
            $byAttribute = $this->configurationValuesByAttribute($row, $configurationAttributeIds);

            foreach ($configurationAttributeIds as $attributeId) {
                if (($byAttribute[$attributeId] ?? null) !== $selections[$attributeId]) {
                    return false;
                }
            }

            return true;
        });
    }
}
