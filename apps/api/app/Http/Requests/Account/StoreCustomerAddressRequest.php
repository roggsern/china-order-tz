<?php

namespace App\Http\Requests\Account;

use App\Rules\E164PhoneNumber;
use Illuminate\Foundation\Http\FormRequest;

class StoreCustomerAddressRequest extends FormRequest
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
            'label' => ['nullable', 'string', 'max:100'],
            'recipient_name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:20', new E164PhoneNumber()],
            'street' => ['required_without:address_line_1', 'nullable', 'string', 'max:255'],
            'address_line_1' => ['required_without:street', 'nullable', 'string', 'max:255'],
            'district' => ['required_without:address_line_2', 'nullable', 'string', 'max:100'],
            'address_line_2' => ['required_without:district', 'nullable', 'string', 'max:100'],
            'city' => ['required', 'string', 'max:100'],
            'region' => ['required', 'string', 'max:100'],
            'country' => ['nullable', 'string', 'max:100'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'is_default' => ['sometimes', 'boolean'],
            'is_shipping' => ['sometimes', 'boolean'],
            'is_billing' => ['sometimes', 'boolean'],
        ];
    }
}
