<?php

namespace App\Http\Requests\Admin;

use App\Enums\WarehouseJobStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexWarehouseJobsRequest extends FormRequest
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
            'status' => ['sometimes', 'nullable', 'string', Rule::enum(WarehouseJobStatus::class)],
            'order_id' => ['sometimes', 'nullable', 'uuid', 'exists:orders,id'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }
}
