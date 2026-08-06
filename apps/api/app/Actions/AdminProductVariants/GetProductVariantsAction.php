<?php

namespace App\Actions\AdminProductVariants;

use App\Enums\CatalogAttributeType;
use App\Http\Resources\AdminCatalogProductVariantResource;
use App\Models\Product;

class GetProductVariantsAction
{
    /**
     * @return array{variants: list<array<string, mixed>>, attributes: list<array<string, mixed>>}
     */
    public function handle(Product $product): array
    {
        $product->loadMissing(['catalogProductType.attributes.options']);

        $variants = $product->variants()
            ->with([
                'catalogAttributeValues.attribute',
                'catalogAttributeValues.option',
                'attributeValues.attribute',
            ])
            ->withCount(['prices', 'inventories', 'chinaCommercialStock as commercial_stocks_count'])
            ->withExists(['chinaCommercialStock as has_active_commercial_stock'])
            ->orderByDesc('is_default')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $attributes = collect($product->catalogProductType?->attributes ?? [])
            ->filter(function ($attribute) {
                $type = $attribute->type instanceof CatalogAttributeType
                    ? $attribute->type
                    : CatalogAttributeType::tryFrom((string) $attribute->type);

                // Include select attributes even with zero options so the wizard can
                // offer inline "+ Add New" for the first option without leaving the page.
                return in_array($type, [CatalogAttributeType::Select, CatalogAttributeType::Multiselect], true);
            })
            ->sortBy(fn ($attribute) => (int) ($attribute->pivot?->sort_order ?? $attribute->sort_order ?? 0))
            ->map(fn ($attribute) => [
                'catalog_attribute_id' => $attribute->id,
                'name' => $attribute->name,
                'slug' => $attribute->slug,
                'type' => $attribute->type instanceof CatalogAttributeType
                    ? $attribute->type->value
                    : (string) $attribute->type,
                'options' => $attribute->options->map(fn ($option) => [
                    'id' => $option->id,
                    'value' => $option->value,
                    'slug' => $option->slug,
                    'sort_order' => (int) $option->sort_order,
                ])->values()->all(),
            ])
            ->values()
            ->all();

        return [
            'variants' => AdminCatalogProductVariantResource::collection($variants)->resolve(),
            'attributes' => $attributes,
        ];
    }
}
