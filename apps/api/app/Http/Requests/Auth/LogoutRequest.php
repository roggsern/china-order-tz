<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Logout remains body-optional for web clients.
 * Mobile may send installation_id / push_token to detach only the current device.
 */
class LogoutRequest extends FormRequest
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
