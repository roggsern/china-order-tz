<?php

namespace App\Services\ProductConfiguration;

use App\Models\Inventory;
use App\Models\Product;
use App\Models\ProductVariant;
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
 */
class ResolveStorefrontConfigurationOptions
{
    public function __construct(
        private readonly AttributeDependencyResolver $dependencyResolver,
        private readonly LoadProductFormSchema $loadProductFormSchema,
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

        $matching = $this->filterMatchingConfigurations($configurations, $selections);

        if ($inStockOnly) {
            $matching = $matching->filter(fn (array $row) => $row['in_stock'])->values();
        }

        $allowedFromConfigs = $this->allowedValuesFromConfigurations($configAttributes, $matching);
        $allowedFromDeps = $type !== null
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

        $matched = $this->findExactMatch($configurations, $selections, $configAttributes->pluck('id')->all());

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
        $variants = ProductVariant::query()
            ->where('product_id', $product->id)
            ->where('is_active', true)
            ->with(['attributeValues.attribute', 'inventory'])
            ->orderBy('name')
            ->get();

        return $variants->map(function (ProductVariant $variant) use ($product) {
            $stock = $this->availableStock($product->id, $variant);

            return [
                'id' => $variant->id,
                'sku' => $variant->sku,
                'name' => $variant->name,
                'price' => $variant->price,
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
                'stock' => $stock,
                'in_stock' => $stock > 0,
            ];
        })->values();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $configurations
     * @param  array<string, string>  $selections
     * @return Collection<int, array<string, mixed>>
     */
    private function filterMatchingConfigurations(Collection $configurations, array $selections): Collection
    {
        if ($selections === []) {
            return $configurations;
        }

        return $configurations->filter(function (array $row) use ($selections) {
            $byAttribute = collect($row['attribute_values'])
                ->mapWithKeys(fn (array $value) => [
                    $value['product_attribute_id'] => $value['id'],
                ]);

            foreach ($selections as $attributeId => $valueId) {
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
     * @param  Collection<int, array<string, mixed>>  $configurations
     * @param  array<string, string>  $selections
     * @param  list<string>  $requiredAttributeIds
     * @return array<string, mixed>|null
     */
    private function findExactMatch(
        Collection $configurations,
        array $selections,
        array $requiredAttributeIds,
    ): ?array {
        if ($requiredAttributeIds === []) {
            return null;
        }

        foreach ($requiredAttributeIds as $attributeId) {
            if (! filled($selections[$attributeId] ?? null)) {
                return null;
            }
        }

        return $configurations->first(function (array $row) use ($selections, $requiredAttributeIds) {
            $byAttribute = collect($row['attribute_values'])
                ->mapWithKeys(fn (array $value) => [
                    $value['product_attribute_id'] => $value['id'],
                ]);

            foreach ($requiredAttributeIds as $attributeId) {
                if (($byAttribute[$attributeId] ?? null) !== $selections[$attributeId]) {
                    return false;
                }
            }

            return count($byAttribute) === count($requiredAttributeIds);
        });
    }

    private function availableStock(string $productId, ProductVariant $variant): int
    {
        if ($variant->relationLoaded('inventory') && $variant->inventory) {
            return max(0, (int) $variant->inventory->availableQuantity());
        }

        $inventory = Inventory::query()
            ->where('product_id', $productId)
            ->where('product_variant_id', $variant->id)
            ->first();

        return max(0, (int) ($inventory?->availableQuantity() ?? 0));
    }
}
