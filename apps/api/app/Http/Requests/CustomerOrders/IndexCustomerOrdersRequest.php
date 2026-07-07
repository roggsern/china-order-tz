<?php

namespace App\Http\Requests\CustomerOrders;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexCustomerOrdersRequest extends FormRequest
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
            'filter' => ['sometimes', 'string', Rule::in(['all', 'active', 'completed'])],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }
}
