<?php

namespace App\Services\Catalog;

use App\Models\ProductVariant;
use App\Models\ProductVariantAttributeValue;

/**
 * Shared display-attribute resolution for product variants.
 * Catalog attribute values always win; legacy attributeValues remain fallback only.
 */
class ProductVariantAttributeResolver
{
    /**
     * @return list<array{attribute: string, value: string}>
     */
    public function resolve(?ProductVariant $variant): array
    {
        if ($variant === null) {
            return [];
        }

        $variant->loadMissing([
            'catalogAttributeValues.attribute',
            'catalogAttributeValues.option',
            'attributeValues.attribute',
        ]);

        $catalogRows = $this->mapCatalogAttributeValues($variant);
        if ($catalogRows !== []) {
            return $catalogRows;
        }

        return $this->mapLegacyAttributeValues($variant);
    }

    /**
     * @return list<array{attribute: string, value: string}>
     */
    private function mapCatalogAttributeValues(ProductVariant $variant): array
    {
        return $variant->catalogAttributeValues
            ->map(function (ProductVariantAttributeValue $row): ?array {
                $attribute = trim((string) (
                    $row->attribute?->name
                    ?? $row->attribute?->slug
                    ?? 'Attribute'
                ));
                $value = $this->catalogAttributeDisplayValue($row);
                if ($attribute === '' || $value === '') {
                    return null;
                }

                return [
                    'attribute' => $attribute,
                    'value' => $value,
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    private function catalogAttributeDisplayValue(ProductVariantAttributeValue $row): string
    {
        $optionValue = trim((string) ($row->option?->value ?? ''));
        if ($optionValue !== '') {
            return $optionValue;
        }

        $text = trim((string) ($row->value_text ?? ''));
        if ($text !== '') {
            return $text;
        }

        if ($row->value_number !== null && $row->value_number !== '') {
            return rtrim(rtrim((string) $row->value_number, '0'), '.') ?: '0';
        }

        if ($row->value_boolean !== null) {
            return $row->value_boolean ? 'Yes' : 'No';
        }

        return '';
    }

    /**
     * @return list<array{attribute: string, value: string}>
     */
    private function mapLegacyAttributeValues(ProductVariant $variant): array
    {
        return $variant->attributeValues
            ->map(fn ($value) => [
                'attribute' => (string) ($value->attribute?->name ?? $value->attribute?->slug ?? 'Attribute'),
                'value' => (string) ($value->value ?? ''),
            ])
            ->filter(fn (array $row) => $row['value'] !== '')
            ->values()
            ->all();
    }
}
