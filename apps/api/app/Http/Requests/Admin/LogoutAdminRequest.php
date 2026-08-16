<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Admin logout may detach the current device push installation only.
 */
class LogoutAdminRequest extends FormRequest
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
            'installation_id' => ['sometimes', 'nullable', 'uuid'],
            'push_token' => ['sometimes', 'nullable', 'string', 'min:16', 'max:512'],
        ];
    }
}
