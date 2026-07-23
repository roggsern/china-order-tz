<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Concerns\AuthorizesAdminPermission;
use App\Support\Admin\AdminPermissions;
use Illuminate\Foundation\Http\FormRequest;

class UpdateProductStockRequest extends FormRequest
{
    use AuthorizesAdminPermission;

    protected function requiredPermission(): string
    {
        return AdminPermissions::INVENTORY_ADJUST;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'stock_quantity' => ['required', 'integer', 'min:0'],
            'idempotency_key' => ['sometimes', 'nullable', 'string', 'max:191'],
        ];
    }
}
