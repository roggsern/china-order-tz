<?php

namespace App\Http\Requests\Admin;

use App\Enums\CustomerLifecycleStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCustomerStatusRequest extends FormRequest
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
            'lifecycle_status' => ['required', Rule::enum(CustomerLifecycleStatus::class)],
            'block_reason' => [
                'nullable',
                'string',
                'max:1000',
                Rule::requiredIf(fn () => $this->input('lifecycle_status') === CustomerLifecycleStatus::Blocked->value),
            ],
        ];
    }
}
