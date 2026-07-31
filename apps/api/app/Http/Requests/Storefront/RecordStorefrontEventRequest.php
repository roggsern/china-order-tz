<?php

namespace App\Http\Requests\Storefront;

use App\Enums\StorefrontEventType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RecordStorefrontEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'visitor_uuid' => ['required', 'string', 'max:36'],
            'session_id' => ['required', 'uuid'],
            'event_type' => ['required', 'string', Rule::in(StorefrontEventType::clientValues())],
            'path' => ['sometimes', 'nullable', 'string', 'max:2048'],
            'product_id' => ['sometimes', 'nullable', 'uuid'],
            'category_id' => ['sometimes', 'nullable', 'uuid'],
            'metadata' => ['sometimes', 'nullable', 'array'],
        ];
    }
}
