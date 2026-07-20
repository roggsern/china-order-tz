<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class IndexFulfillmentsRequest extends FormRequest
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
            'strategy' => ['sometimes', 'nullable', 'string', 'in:local,china'],
            'status' => ['sometimes', 'nullable', 'string'],
            'order_id' => ['sometimes', 'nullable', 'uuid', 'exists:orders,id'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }
}
