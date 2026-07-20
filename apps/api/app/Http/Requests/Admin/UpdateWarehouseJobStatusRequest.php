<?php

namespace App\Http\Requests\Admin;

use App\Enums\WarehouseJobStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateWarehouseJobStatusRequest extends FormRequest
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
            'status' => ['required', 'string', Rule::enum(WarehouseJobStatus::class)],
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ];
    }
}
