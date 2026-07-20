<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreSupplierProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'product_variant_id' => ['required', 'uuid', 'exists:product_variants,id'],
            'supplier_sku' => ['nullable', 'string', 'max:100'],
            'purchase_cost' => ['required', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3'],
            'lead_time_days' => ['nullable', 'integer', 'min:0', 'max:3650'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
