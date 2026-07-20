<?php

namespace App\Http\Requests\Admin;

use App\Enums\ReturnRequestStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateReturnRequestStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(ReturnRequestStatus::class)],
            'admin_notes' => ['nullable', 'string', 'max:2000'],
            'items' => ['sometimes', 'array'],
            'items.*.id' => ['required_with:items', 'uuid'],
            'items.*.condition' => ['nullable', 'string', 'max:100'],
            'items.*.resolution' => ['nullable', 'string', 'max:50'],
            'items.*.refund_amount' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
