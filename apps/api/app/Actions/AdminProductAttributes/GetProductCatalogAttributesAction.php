<?php

namespace App\Actions\AdminProductAttributes;

use App\Enums\CatalogAttributeType;
use App\Models\CatalogProductAttributeValue;
use App\Models\Product;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class GetProductCatalogAttributesAction
{
    /**
     * @return list<array<string, mixed>>
     */
    public function handle(Product $product): array
    {
        $product->loadMissing(['catalogProductType.attributes.options', 'catalogAttributeValues.option']);

        $catalogType = $product->catalogProductType;

        if ($catalogType === null) {
            throw ValidationException::withMessages([
                'catalog_product_type_id' => ['Product must have a catalog product type before managing specifications.'],
            ]);
        }

        $assigned = $catalogType->attributes;
        $existing = $product->catalogAttributeValues
            ->where('is_active', true)
            ->groupBy('catalog_attribute_id');

        return $assigned->map(function ($attribute) use ($existing) {
            /** @var Collection<int, CatalogProductAttributeValue> $rows */
            $rows = $existing->get($attribute->id, collect());
            $type = $attribute->type instanceof CatalogAttributeType
                ? $attribute->type
                : CatalogAttributeType::from((string) $attribute->type);

            $value = [
                'value_text' => null,
                'value_number' => null,
                'value_boolean' => null,
                'option_id' => null,
                'option_ids' => [],
                'display' => null,
            ];

            if ($type === CatalogAttributeType::Multiselect) {
                $optionIds = $rows->pluck('option_id')->filter()->values()->all();
                $labels = $rows->map(fn (CatalogProductAttributeValue $row) => $row->option?->value)
                    ->filter()
                    ->values()
                    ->all();
                $value['option_ids'] = $optionIds;
                $value['display'] = $labels !== [] ? implode(', ', $labels) : null;
            } elseif ($rows->isNotEmpty()) {
                $row = $rows->first();
                $value['value_text'] = $row->value_text;
                $value['value_number'] = $row->value_number !== null ? (float) $row->value_number : null;
                $value['value_boolean'] = $row->value_boolean;
                $value['option_id'] = $row->option_id;
                $value['display'] = match ($type) {
                    CatalogAttributeType::Select => $row->option?->value,
                    CatalogAttributeType::Boolean => $row->value_boolean === null
                        ? null
                        : ($row->value_boolean ? 'Yes' : 'No'),
                    CatalogAttributeType::Number => $row->value_number !== null
                        ? rtrim(rtrim(number_format((float) $row->value_number, 4, '.', ''), '0'), '.').($attribute->unit ? ' '.$attribute->unit : '')
                        : null,
                    default => $row->value_text,
                };
            }

            $isRequired = (bool) ($attribute->pivot?->is_required || $attribute->is_required);

            return [
                'catalog_attribute_id' => $attribute->id,
                'name' => $attribute->name,
                'slug' => $attribute->slug,
                'type' => $type->value,
                'unit' => $attribute->unit,
                'is_required' => $isRequired,
                'is_filterable' => (bool) $attribute->is_filterable,
                'sort_order' => (int) ($attribute->pivot?->sort_order ?? $attribute->sort_order ?? 0),
                'options' => $attribute->options->map(fn ($option) => [
                    'id' => $option->id,
                    'value' => $option->value,
                    'slug' => $option->slug,
                    'sort_order' => (int) $option->sort_order,
                ])->values()->all(),
                'value' => $value,
            ];
        })->sortBy('sort_order')->values()->all();
    }
}
