<?php

namespace App\Http\Requests\Admin;

use App\Enums\PurchaseOrderStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePurchaseOrderStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'status' => ['required', 'string', Rule::enum(PurchaseOrderStatus::class)],
            'notes' => ['nullable', 'string'],
        ];
    }
}
