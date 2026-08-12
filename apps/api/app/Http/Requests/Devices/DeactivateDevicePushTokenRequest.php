<?php

namespace App\Http\Requests\Devices;

use Illuminate\Foundation\Http\FormRequest;

class DeactivateDevicePushTokenRequest extends FormRequest
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
            'installation_id' => ['required_without:push_token', 'nullable', 'uuid'],
            'push_token' => ['required_without:installation_id', 'nullable', 'string', 'min:16', 'max:512'],
        ];
    }
}
