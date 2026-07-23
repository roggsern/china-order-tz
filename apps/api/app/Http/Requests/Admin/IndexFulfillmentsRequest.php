<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Concerns\AuthorizesAdminPermission;
use App\Support\Admin\AdminPermissions;
use Illuminate\Foundation\Http\FormRequest;

class IndexFulfillmentsRequest extends FormRequest
{
    use AuthorizesAdminPermission;

    protected function requiredPermission(): string
    {
        return AdminPermissions::ORDERS_VIEW;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'strategy' => ['sometimes', 'nullable', 'string', 'in:local,china'],
            'status' => ['sometimes', 'nullable', 'string'],
            'order_id' => ['sometimes', 'nullable', 'uuid', 'exists:orders,id'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }
}
