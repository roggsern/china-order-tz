<?php

namespace App\Http\Requests\Admin;

use App\Enums\FulfillmentStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateFulfillmentStatusRequest extends FormRequest
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
            'status' => ['sometimes', 'required', Rule::enum(FulfillmentStatus::class)],
            'assigned_to' => ['sometimes', 'nullable', 'uuid', 'exists:admins,id'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ];
    }
}
