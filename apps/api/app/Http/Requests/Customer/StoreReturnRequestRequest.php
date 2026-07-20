<?php

namespace App\Http\Requests\Customer;

use Illuminate\Foundation\Http\FormRequest;

class StoreReturnRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'customer_notes' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.order_item_id' => ['required', 'uuid', 'exists:order_items,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.reason' => ['nullable', 'string', 'max:255'],
            'items.*.replacement_requested' => ['sometimes', 'boolean'],
        ];
    }
}
