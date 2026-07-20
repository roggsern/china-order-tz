<?php

namespace App\Http\Requests\Admin;

use App\Enums\TrackingEventType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreShipmentTrackingEventRequest extends FormRequest
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
            'event_type' => ['required', 'string', Rule::enum(TrackingEventType::class)],
            'description' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'location' => ['sometimes', 'nullable', 'string', 'max:255'],
            'event_at' => ['sometimes', 'nullable', 'date'],
        ];
    }
}
