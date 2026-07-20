<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class SyncCatalogProductTypeAttributesRequest extends FormRequest
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
            'attributes' => ['required', 'array'],
            'attributes.*.catalog_attribute_id' => ['required', 'uuid', 'exists:catalog_attributes,id'],
            'attributes.*.is_required' => ['sometimes', 'boolean'],
            'attributes.*.sort_order' => ['sometimes', 'integer', 'min:0'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $attributes = $this->input('attributes');
        if (! is_array($attributes)) {
            return;
        }

        $normalized = [];
        foreach ($attributes as $row) {
            if (! is_array($row)) {
                continue;
            }
            if (array_key_exists('is_required', $row) && ! is_bool($row['is_required'])) {
                $row['is_required'] = filter_var($row['is_required'], FILTER_VALIDATE_BOOLEAN);
            }
            $normalized[] = $row;
        }

        $this->merge(['attributes' => $normalized]);
    }
}
