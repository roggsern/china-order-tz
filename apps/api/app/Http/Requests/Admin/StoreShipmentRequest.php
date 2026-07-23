<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Concerns\AuthorizesAdminPermission;
use App\Support\Admin\AdminPermissions;
use Illuminate\Foundation\Http\FormRequest;

class StoreShipmentRequest extends FormRequest
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
            'carrier_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'tracking_reference' => ['sometimes', 'nullable', 'string', 'max:255'],
            'origin' => ['sometimes', 'nullable', 'string', 'max:255'],
            'destination' => ['sometimes', 'nullable', 'string', 'max:255'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ];
    }
}
