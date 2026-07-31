<?php

namespace App\Http\Requests\Storefront;

use Illuminate\Foundation\Http\FormRequest;

class IdentifyStorefrontVisitorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'visitor_uuid' => ['sometimes', 'nullable', 'string', 'max:36'],
            'session_id' => ['sometimes', 'nullable', 'uuid'],
        ];
    }
}
