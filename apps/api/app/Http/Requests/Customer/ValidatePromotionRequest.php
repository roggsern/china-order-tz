<?php

namespace App\Http\Requests\Customer;

use Illuminate\Foundation\Http\FormRequest;

class ValidatePromotionRequest extends FormRequest
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
            'code' => ['required', 'string', 'max:64'],
            'checkout_session_id' => ['nullable', 'uuid', 'exists:checkout_sessions,id'],
        ];
    }
}
