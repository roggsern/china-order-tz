<?php

namespace App\Http\Requests\Account;

use App\Rules\E164PhoneNumber;
use Illuminate\Foundation\Http\FormRequest;

class UpdateCustomerAddressRequest extends FormRequest
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
            'label' => ['sometimes', 'nullable', 'string', 'max:100'],
            'recipient_name' => ['sometimes', 'string', 'max:255'],
            'phone' => ['sometimes', 'string', 'max:20', new E164PhoneNumber()],
            'street' => ['sometimes', 'string', 'max:255'],
            'address_line_1' => ['sometimes', 'string', 'max:255'],
            'district' => ['sometimes', 'nullable', 'string', 'max:100'],
            'address_line_2' => ['sometimes', 'nullable', 'string', 'max:100'],
            'city' => ['sometimes', 'string', 'max:100'],
            'region' => ['sometimes', 'string', 'max:100'],
            'country' => ['sometimes', 'string', 'max:100'],
            'postal_code' => ['sometimes', 'nullable', 'string', 'max:20'],
            'is_default' => ['sometimes', 'boolean'],
            'is_shipping' => ['sometimes', 'boolean'],
            'is_billing' => ['sometimes', 'boolean'],
        ];
    }
}
