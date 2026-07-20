<?php

namespace App\Http\Resources;

use App\Enums\CatalogAttributeType;
use App\Models\ProductVariant;
use App\Models\ProductVariantAttributeValue;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ProductVariant */
class AdminCatalogProductVariantResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $values = $this->relationLoaded('catalogAttributeValues')
            ? $this->catalogAttributeValues
            : collect();

        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'name' => $this->name,
            'sku' => $this->sku,
            'barcode' => $this->barcode,
            'status' => $this->is_active ? 'active' : 'inactive',
            'is_active' => (bool) $this->is_active,
            'is_default' => (bool) $this->is_default,
            'sort_order' => (int) $this->sort_order,
            // Legacy product_variants.price is not the Pricing Engine source of truth.
            'price' => null,
            'stock' => null,
            'prices_count' => $this->whenCounted('prices'),
            'inventories_count' => $this->whenCounted('inventories'),
            'attribute_values' => $values->map(function (ProductVariantAttributeValue $row) {
                $type = $row->attribute?->type instanceof CatalogAttributeType
                    ? $row->attribute->type
                    : CatalogAttributeType::tryFrom((string) ($row->attribute?->type ?? 'text'));

                $display = match ($type) {
                    CatalogAttributeType::Select, CatalogAttributeType::Multiselect => $row->option?->value
                        ?? $row->value_text,
                    CatalogAttributeType::Boolean => $row->value_boolean === null
                        ? null
                        : ($row->value_boolean ? 'Yes' : 'No'),
                    CatalogAttributeType::Number => $row->value_number !== null
                        ? rtrim(rtrim(number_format((float) $row->value_number, 4, '.', ''), '0'), '.')
                        : null,
                    default => $row->value_text,
                };

                return [
                    'id' => $row->id,
                    'catalog_attribute_id' => $row->catalog_attribute_id,
                    'attribute_name' => $row->attribute?->name,
                    'attribute_slug' => $row->attribute?->slug,
                    'type' => $type?->value ?? 'text',
                    'option_id' => $row->option_id,
                    'option_value' => $row->option?->value,
                    'value_text' => $row->value_text,
                    'value_number' => $row->value_number !== null ? (float) $row->value_number : null,
                    'value_boolean' => $row->value_boolean,
                    'display' => $display,
                ];
            })->values()->all(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
