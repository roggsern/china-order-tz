<?php

namespace App\Actions\AdminProductAttributes;

use App\Enums\CatalogAttributeType;
use App\Http\Requests\Admin\SyncProductCatalogAttributesRequest;
use App\Models\CatalogAttribute;
use App\Models\CatalogAttributeOption;
use App\Models\CatalogProductAttributeValue;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SyncProductCatalogAttributesAction
{
    public function __construct(
        private readonly GetProductCatalogAttributesAction $getProductCatalogAttributes,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function handle(SyncProductCatalogAttributesRequest $request, Product $product): array
    {
        $product->loadMissing(['catalogProductType.attributes.options']);

        $catalogType = $product->catalogProductType;
        if ($catalogType === null) {
            throw ValidationException::withMessages([
                'catalog_product_type_id' => ['Product must have a catalog product type before managing specifications.'],
            ]);
        }

        /** @var \Illuminate\Support\Collection<string, CatalogAttribute> $assignedById */
        $assignedById = $catalogType->attributes->keyBy('id');
        $payload = $request->validated('attributes') ?? [];

        DB::transaction(function () use ($product, $assignedById, $payload) {
            $errors = [];
            $submittedIds = [];

            foreach ($payload as $index => $row) {
                $attributeId = $row['catalog_attribute_id'];
                $submittedIds[] = $attributeId;

                $attribute = $assignedById->get($attributeId);
                if ($attribute === null) {
                    $errors["attributes.$index.catalog_attribute_id"] = [
                        'Attribute is not assigned to this product type.',
                    ];
                    continue;
                }

                try {
                    $this->syncAttributeRows($product, $attribute, $row);
                } catch (ValidationException $exception) {
                    foreach ($exception->errors() as $key => $messages) {
                        $errors["attributes.$index.$key"] = $messages;
                    }
                }
            }

            // PUT replaces the full specification set for assigned attributes.
            foreach ($assignedById as $attributeId => $attribute) {
                if (! in_array($attributeId, $submittedIds, true)) {
                    CatalogProductAttributeValue::query()
                        ->where('product_id', $product->id)
                        ->where('catalog_attribute_id', $attributeId)
                        ->delete();
                }
            }

            // Enforce required attributes for the product type.
            foreach ($assignedById as $attribute) {
                $isRequired = (bool) ($attribute->pivot?->is_required || $attribute->is_required);
                if (! $isRequired) {
                    continue;
                }

                $hasValue = CatalogProductAttributeValue::query()
                    ->where('product_id', $product->id)
                    ->where('catalog_attribute_id', $attribute->id)
                    ->where('is_active', true)
                    ->where(function ($query) {
                        $query->whereNotNull('value_text')
                            ->orWhereNotNull('value_number')
                            ->orWhereNotNull('value_boolean')
                            ->orWhereNotNull('option_id');
                    })
                    ->exists();

                if (! $hasValue) {
                    $errors['attributes'][] = "Required attribute “{$attribute->name}” is missing a value.";
                }
            }

            if ($errors !== []) {
                throw ValidationException::withMessages($errors);
            }
        });

        return $this->getProductCatalogAttributes->handle($product->fresh([
            'catalogProductType.attributes.options',
            'catalogAttributeValues.option',
        ]));
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function syncAttributeRows(Product $product, CatalogAttribute $attribute, array $row): void
    {
        $type = $attribute->type instanceof CatalogAttributeType
            ? $attribute->type
            : CatalogAttributeType::from((string) $attribute->type);

        // Clear previous rows for this attribute, then rewrite.
        CatalogProductAttributeValue::query()
            ->where('product_id', $product->id)
            ->where('catalog_attribute_id', $attribute->id)
            ->delete();

        if ($this->rowIsEmpty($row)) {
            return;
        }

        $isActive = array_key_exists('is_active', $row) ? (bool) $row['is_active'] : true;

        match ($type) {
            CatalogAttributeType::Text => $this->createText($product, $attribute, $row, $isActive),
            CatalogAttributeType::Number => $this->createNumber($product, $attribute, $row, $isActive),
            CatalogAttributeType::Boolean => $this->createBoolean($product, $attribute, $row, $isActive),
            CatalogAttributeType::Select => $this->createSelect($product, $attribute, $row, $isActive),
            CatalogAttributeType::Multiselect => $this->createMultiselect($product, $attribute, $row, $isActive),
        };
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function rowIsEmpty(array $row): bool
    {
        $hasText = filled($row['value_text'] ?? null);
        $hasNumber = array_key_exists('value_number', $row) && $row['value_number'] !== null && $row['value_number'] !== '';
        $hasBoolean = array_key_exists('value_boolean', $row) && $row['value_boolean'] !== null;
        $hasOption = filled($row['option_id'] ?? null);
        $hasOptions = is_array($row['option_ids'] ?? null) && ($row['option_ids'] ?? []) !== [];

        return ! $hasText && ! $hasNumber && ! $hasBoolean && ! $hasOption && ! $hasOptions;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function createText(Product $product, CatalogAttribute $attribute, array $row, bool $isActive): void
    {
        if (! filled($row['value_text'] ?? null)) {
            throw ValidationException::withMessages([
                'value_text' => ['Text value is required for '.$attribute->name.'.'],
            ]);
        }

        if (array_key_exists('value_number', $row) && $row['value_number'] !== null) {
            throw ValidationException::withMessages([
                'value_number' => ['Number values are only allowed for number attributes.'],
            ]);
        }

        if (array_key_exists('value_boolean', $row) && $row['value_boolean'] !== null) {
            throw ValidationException::withMessages([
                'value_boolean' => ['Boolean values are only allowed for boolean attributes.'],
            ]);
        }

        CatalogProductAttributeValue::query()->create([
            'product_id' => $product->id,
            'catalog_attribute_id' => $attribute->id,
            'value_text' => (string) $row['value_text'],
            'is_active' => $isActive,
        ]);
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function createNumber(Product $product, CatalogAttribute $attribute, array $row, bool $isActive): void
    {
        if (! array_key_exists('value_number', $row) || $row['value_number'] === null || $row['value_number'] === '') {
            throw ValidationException::withMessages([
                'value_number' => ['Number value is required for '.$attribute->name.'.'],
            ]);
        }

        if (! is_numeric($row['value_number'])) {
            throw ValidationException::withMessages([
                'value_number' => ['Number values only for number attributes.'],
            ]);
        }

        CatalogProductAttributeValue::query()->create([
            'product_id' => $product->id,
            'catalog_attribute_id' => $attribute->id,
            'value_number' => $row['value_number'],
            'value_text' => (string) $row['value_number'],
            'is_active' => $isActive,
        ]);
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function createBoolean(Product $product, CatalogAttribute $attribute, array $row, bool $isActive): void
    {
        if (! array_key_exists('value_boolean', $row) || $row['value_boolean'] === null) {
            throw ValidationException::withMessages([
                'value_boolean' => ['Boolean value is required for '.$attribute->name.'.'],
            ]);
        }

        if (! is_bool($row['value_boolean'])) {
            throw ValidationException::withMessages([
                'value_boolean' => ['Boolean only for boolean attributes.'],
            ]);
        }

        CatalogProductAttributeValue::query()->create([
            'product_id' => $product->id,
            'catalog_attribute_id' => $attribute->id,
            'value_boolean' => $row['value_boolean'],
            'value_text' => $row['value_boolean'] ? 'Yes' : 'No',
            'is_active' => $isActive,
        ]);
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function createSelect(Product $product, CatalogAttribute $attribute, array $row, bool $isActive): void
    {
        $optionId = $row['option_id'] ?? null;
        if (! filled($optionId)) {
            throw ValidationException::withMessages([
                'option_id' => ['Select value is required for '.$attribute->name.'.'],
            ]);
        }

        $option = CatalogAttributeOption::query()
            ->whereKey($optionId)
            ->where('catalog_attribute_id', $attribute->id)
            ->first();

        if ($option === null) {
            throw ValidationException::withMessages([
                'option_id' => ['Select values must exist in attribute options.'],
            ]);
        }

        CatalogProductAttributeValue::query()->create([
            'product_id' => $product->id,
            'catalog_attribute_id' => $attribute->id,
            'option_id' => $option->id,
            'value_text' => $option->value,
            'is_active' => $isActive,
        ]);
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function createMultiselect(Product $product, CatalogAttribute $attribute, array $row, bool $isActive): void
    {
        $optionIds = $row['option_ids'] ?? [];
        if (! is_array($optionIds) || $optionIds === []) {
            throw ValidationException::withMessages([
                'option_ids' => ['At least one option is required for '.$attribute->name.'.'],
            ]);
        }

        $options = CatalogAttributeOption::query()
            ->where('catalog_attribute_id', $attribute->id)
            ->whereIn('id', $optionIds)
            ->get()
            ->keyBy('id');

        if ($options->count() !== count(array_unique($optionIds))) {
            throw ValidationException::withMessages([
                'option_ids' => ['Select values must exist in attribute options.'],
            ]);
        }

        foreach ($optionIds as $optionId) {
            $option = $options->get($optionId);
            CatalogProductAttributeValue::query()->create([
                'product_id' => $product->id,
                'catalog_attribute_id' => $attribute->id,
                'option_id' => $option->id,
                'value_text' => $option->value,
                'is_active' => $isActive,
            ]);
        }
    }
}
