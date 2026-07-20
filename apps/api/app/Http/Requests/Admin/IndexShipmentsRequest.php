<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class IndexShipmentsRequest extends FormRequest
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
            'status' => ['sometimes', 'nullable', 'string'],
            'transport_mode' => ['sometimes', 'nullable', 'string', 'in:air,sea,road'],
            'order_id' => ['sometimes', 'nullable', 'uuid', 'exists:orders,id'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }
}
