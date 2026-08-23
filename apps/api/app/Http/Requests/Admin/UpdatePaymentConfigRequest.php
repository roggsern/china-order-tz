<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Concerns\AuthorizesAdminPermission;
use App\Support\Admin\AdminPermissions;
use Illuminate\Foundation\Http\FormRequest;

class UpdatePaymentConfigRequest extends FormRequest
{
    use AuthorizesAdminPermission;

    protected function requiredPermission(): string
    {
        return AdminPermissions::PAYMENTS_CONFIG_MANAGE;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'default_provider' => ['sometimes', 'string'],
            'enabled_methods' => ['sometimes', 'array'],
            'enabled_methods.nmb' => ['sometimes', 'boolean'],
            'enabled_methods.snippe' => ['sometimes', 'boolean'],
            'enabled_methods.mpesa' => ['sometimes', 'boolean'],
            'enabled_methods.card' => ['sometimes', 'boolean'],
            'enabled_methods.cash' => ['sometimes', 'boolean'],
            'enabled_methods.bank_transfer' => ['sometimes', 'boolean'],
        ];
    }
}
