<?php

namespace App\Http\Requests\Admin;

use App\Enums\ShipmentStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateShipmentStatusRequest extends FormRequest
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
            'shipment_status' => ['required', 'string', Rule::enum(ShipmentStatus::class)],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('shipment_status')) {
            $this->merge([
                'shipment_status' => strtolower((string) $this->input('shipment_status')),
            ]);
        }
    }
}
