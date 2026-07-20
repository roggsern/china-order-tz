<?php

namespace App\Services\Catalog;

use App\Enums\CatalogAttributeType;
use App\Models\CatalogAttribute;
use App\Models\CatalogAttributeOption;
use App\Models\ProductVariant;
use App\Models\ProductVariantAttributeValue;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class SyncVariantCatalogAttributeValues
{
    /**
     * Replace catalog attribute values for a variant.
     *
     * @param  list<array<string, mixed>>  $rows
     * @param  Collection<string, CatalogAttribute>|null  $allowedById
     */
    public function handle(ProductVariant $variant, array $rows, ?Collection $allowedById = null): void
    {
        $errors = [];

        foreach ($rows as $index => $row) {
            $attributeId = $row['catalog_attribute_id'] ?? null;
            if (! is_string($attributeId) || $attributeId === '') {
                $errors["attribute_values.$index.catalog_attribute_id"] = ['Attribute is required.'];
                continue;
            }

            $attribute = $allowedById?->get($attributeId)
                ?? CatalogAttribute::query()->with('options')->find($attributeId);

            if ($attribute === null) {
                $errors["attribute_values.$index.catalog_attribute_id"] = ['Attribute not found.'];
                continue;
            }

            if ($allowedById !== null && ! $allowedById->has($attributeId)) {
                $errors["attribute_values.$index.catalog_attribute_id"] = [
                    'Attribute is not assigned to this product type.',
                ];
                continue;
            }

            try {
                $this->assertTypedValue($attribute, $row, $index);
            } catch (ValidationException $exception) {
                foreach ($exception->errors() as $key => $messages) {
                    $errors[$key] = $messages;
                }
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        ProductVariantAttributeValue::query()
            ->where('product_variant_id', $variant->id)
            ->delete();

        foreach ($rows as $row) {
            $attribute = $allowedById?->get($row['catalog_attribute_id'])
                ?? CatalogAttribute::query()->with('options')->findOrFail($row['catalog_attribute_id']);

            $type = $attribute->type instanceof CatalogAttributeType
                ? $attribute->type
                : CatalogAttributeType::from((string) $attribute->type);

            if ($type === CatalogAttributeType::Select) {
                $option = CatalogAttributeOption::query()->find($row['option_id']);
                ProductVariantAttributeValue::query()->create([
                    'product_variant_id' => $variant->id,
                    'catalog_attribute_id' => $attribute->id,
                    'option_id' => $option?->id,
                    'value_text' => $option?->value,
                    'value_number' => null,
                    'value_boolean' => null,
                ]);
                continue;
            }

            ProductVariantAttributeValue::query()->create([
                'product_variant_id' => $variant->id,
                'catalog_attribute_id' => $attribute->id,
                'option_id' => $row['option_id'] ?? null,
                'value_text' => $row['value_text'] ?? null,
                'value_number' => $row['value_number'] ?? null,
                'value_boolean' => $row['value_boolean'] ?? null,
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function assertTypedValue(CatalogAttribute $attribute, array $row, int $index): void
    {
        $type = $attribute->type instanceof CatalogAttributeType
            ? $attribute->type
            : CatalogAttributeType::from((string) $attribute->type);

        $prefix = "attribute_values.$index";

        match ($type) {
            CatalogAttributeType::Select => $this->assertSelect($attribute, $row, $prefix),
            CatalogAttributeType::Number => $this->assertNumber($row, $prefix),
            CatalogAttributeType::Boolean => $this->assertBoolean($row, $prefix),
            CatalogAttributeType::Text => $this->assertText($row, $prefix),
            CatalogAttributeType::Multiselect => throw ValidationException::withMessages([
                "$prefix.catalog_attribute_id" => ['Multiselect attributes cannot be used on a single variant row. Use select options per combination.'],
            ]),
        };
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function assertSelect(CatalogAttribute $attribute, array $row, string $prefix): void
    {
        $optionId = $row['option_id'] ?? null;
        if (! is_string($optionId) || $optionId === '') {
            throw ValidationException::withMessages([
                "$prefix.option_id" => ['Select attributes require an option_id.'],
            ]);
        }

        $exists = $attribute->options->contains(fn (CatalogAttributeOption $option) => $option->id === $optionId)
            || CatalogAttributeOption::query()
                ->where('id', $optionId)
                ->where('catalog_attribute_id', $attribute->id)
                ->exists();

        if (! $exists) {
            throw ValidationException::withMessages([
                "$prefix.option_id" => ['Selected option does not belong to this attribute.'],
            ]);
        }

        if (array_key_exists('value_number', $row) && $row['value_number'] !== null) {
            throw ValidationException::withMessages([
                "$prefix.value_number" => ['Number values are not allowed for select attributes.'],
            ]);
        }

        if (array_key_exists('value_boolean', $row) && $row['value_boolean'] !== null) {
            throw ValidationException::withMessages([
                "$prefix.value_boolean" => ['Boolean values are not allowed for select attributes.'],
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function assertNumber(array $row, string $prefix): void
    {
        if (! array_key_exists('value_number', $row) || $row['value_number'] === null || $row['value_number'] === '') {
            throw ValidationException::withMessages([
                "$prefix.value_number" => ['Number attributes require value_number.'],
            ]);
        }

        if (! is_numeric($row['value_number'])) {
            throw ValidationException::withMessages([
                "$prefix.value_number" => ['value_number must be numeric.'],
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function assertBoolean(array $row, string $prefix): void
    {
        if (! array_key_exists('value_boolean', $row) || $row['value_boolean'] === null) {
            throw ValidationException::withMessages([
                "$prefix.value_boolean" => ['Boolean attributes require value_boolean.'],
            ]);
        }

        if (! is_bool($row['value_boolean'])) {
            throw ValidationException::withMessages([
                "$prefix.value_boolean" => ['value_boolean must be true or false.'],
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function assertText(array $row, string $prefix): void
    {
        $text = $row['value_text'] ?? null;
        if (! is_string($text) || trim($text) === '') {
            throw ValidationException::withMessages([
                "$prefix.value_text" => ['Text attributes require value_text.'],
            ]);
        }
    }
}
