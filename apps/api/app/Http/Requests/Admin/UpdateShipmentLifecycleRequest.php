<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Metadata-only updates. Shipment lifecycle status comes from tracking events.
 */
class UpdateShipmentLifecycleRequest extends FormRequest
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
            'carrier_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'tracking_reference' => ['sometimes', 'nullable', 'string', 'max:255'],
            'origin' => ['sometimes', 'nullable', 'string', 'max:255'],
            'destination' => ['sometimes', 'nullable', 'string', 'max:255'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
            // Rejected by ShipmentEngine if present — status is event-derived.
            'status' => ['prohibited'],
        ];
    }
}
