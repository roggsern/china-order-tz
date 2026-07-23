<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Concerns\AuthorizesAdminPermission;
use App\Support\Admin\AdminPermissions;
use App\Enums\TrackingEventType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreShipmentTrackingEventRequest extends FormRequest
{
    use AuthorizesAdminPermission;

    protected function requiredPermission(): string
    {
        return AdminPermissions::ORDERS_SHIP;
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
