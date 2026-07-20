<?php

namespace App\Http\Requests\Profile;

use App\Rules\E164PhoneNumber;
use Illuminate\Foundation\Http\FormRequest;

class UpdateDeliveryAddressRequest extends FormRequest
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
            'recipient_name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:20', new E164PhoneNumber()],
            'country' => ['required', 'string', 'max:100'],
            'region' => ['required', 'string', 'max:100'],
            'city' => ['required', 'string', 'max:100'],
            'district' => ['required', 'string', 'max:100'],
            'street' => ['required', 'string', 'max:255'],
            'landmark' => ['nullable', 'string', 'max:255'],
            'postal_code' => ['nullable', 'string', 'max:20'],
        ];
    }
}
