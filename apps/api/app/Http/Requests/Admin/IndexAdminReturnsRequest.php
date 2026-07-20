<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class IndexAdminReturnsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'status' => ['sometimes', 'string', 'max:50'],
            'order_id' => ['sometimes', 'uuid'],
            'customer_id' => ['sometimes', 'uuid'],
        ];
    }
}
