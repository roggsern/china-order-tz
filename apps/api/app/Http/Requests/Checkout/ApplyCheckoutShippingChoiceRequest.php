<?php

namespace App\Http\Requests\Checkout;

use App\Enums\DeliveryType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ApplyCheckoutShippingChoiceRequest extends FormRequest
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
            'shipping_choice' => ['required', 'string', Rule::enum(DeliveryType::class)],
            'shipping_method' => ['nullable', 'string', Rule::in(['air', 'sea'])],
            'agent_name' => ['nullable', 'string', 'max:255'],
            'agent_contact' => ['nullable', 'string', 'max:255'],
        ];
    }
}
