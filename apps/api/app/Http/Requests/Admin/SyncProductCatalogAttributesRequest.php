<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class SyncProductCatalogAttributesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'attributes' => ['present', 'array'],
            'attributes.*.catalog_attribute_id' => ['required', 'uuid', 'exists:catalog_attributes,id'],
            'attributes.*.value_text' => ['sometimes', 'nullable', 'string'],
            'attributes.*.value_number' => ['sometimes', 'nullable', 'numeric'],
            'attributes.*.value_boolean' => ['sometimes', 'nullable', 'boolean'],
            'attributes.*.option_id' => ['sometimes', 'nullable', 'uuid', 'exists:catalog_attribute_options,id'],
            'attributes.*.option_ids' => ['sometimes', 'nullable', 'array'],
            'attributes.*.option_ids.*' => ['uuid', 'exists:catalog_attribute_options,id'],
            'attributes.*.is_active' => ['sometimes', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $attributes = $this->input('attributes');

        if (! is_array($attributes)) {
            return;
        }

        foreach ($attributes as $index => $row) {
            if (! is_array($row)) {
                continue;
            }

            if (array_key_exists('value_boolean', $row) && ! is_bool($row['value_boolean']) && $row['value_boolean'] !== null) {
                $attributes[$index]['value_boolean'] = filter_var(
                    $row['value_boolean'],
                    FILTER_VALIDATE_BOOLEAN,
                    FILTER_NULL_ON_FAILURE,
                );
            }

            if (array_key_exists('is_active', $row) && ! is_bool($row['is_active'])) {
                $attributes[$index]['is_active'] = filter_var($row['is_active'], FILTER_VALIDATE_BOOLEAN);
            }
        }

        $this->merge(['attributes' => $attributes]);
    }
}
