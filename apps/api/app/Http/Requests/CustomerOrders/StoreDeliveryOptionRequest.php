<?php

namespace App\Http\Requests\CustomerOrders;

use App\Enums\DeliveryOptionStatus;
use App\Enums\DeliveryShippingMethod;
use App\Enums\DeliveryType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDeliveryOptionRequest extends FormRequest
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
            'delivery_type' => ['required', 'string', Rule::enum(DeliveryType::class)],
            'shipping_method' => ['sometimes', 'nullable', 'string', Rule::enum(DeliveryShippingMethod::class)],
            'agent_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'agent_contact' => ['sometimes', 'nullable', 'string', 'max:255'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ];
    }
}
