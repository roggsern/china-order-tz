<?php

namespace App\Http\Requests\Devices;

use App\Enums\PushTokenPlatform;
use App\Enums\PushTokenProvider;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegisterDevicePushTokenRequest extends FormRequest
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
            'push_token' => ['required', 'string', 'min:16', 'max:512'],
            'provider' => ['required', 'string', Rule::enum(PushTokenProvider::class)],
            'platform' => ['required', 'string', Rule::enum(PushTokenPlatform::class)],
            'installation_id' => ['required', 'uuid'],
            'app_version' => ['sometimes', 'nullable', 'string', 'max:64'],
            'device_name' => ['sometimes', 'nullable', 'string', 'max:120'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function validatedPayload(): array
    {
        /** @var array{
         *   push_token: string,
         *   provider: string,
         *   platform: string,
         *   installation_id: string,
         *   app_version?: string|null,
         *   device_name?: string|null
         * } $data
         */
        $data = $this->validated();

        return $data;
    }
}
