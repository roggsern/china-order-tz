<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Concerns\AuthorizesAdminPermission;
use App\Support\Admin\AdminPermissions;
use Illuminate\Foundation\Http\FormRequest;

class UpdateShippingRateRequest extends FormRequest
{
    use AuthorizesAdminPermission;

    protected function requiredPermission(): string
    {
        return AdminPermissions::SHIPPING_MANAGE;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'price' => ['sometimes', 'numeric', 'min:0'],
            'estimated_min_days' => ['sometimes', 'integer', 'min:0', 'max:365'],
            'estimated_max_days' => ['sometimes', 'integer', 'min:0', 'max:365'],
            'estimated_delivery_days' => ['sometimes', 'integer', 'min:0', 'max:365'],
            'active' => ['sometimes', 'boolean'],
        ];
    }
}
