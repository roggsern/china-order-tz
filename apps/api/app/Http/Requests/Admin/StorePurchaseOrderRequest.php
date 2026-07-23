<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Concerns\AuthorizesAdminPermission;
use App\Support\Admin\AdminPermissions;
use Illuminate\Foundation\Http\FormRequest;

class StorePurchaseOrderRequest extends FormRequest
{
    use AuthorizesAdminPermission;

    protected function requiredPermission(): string
    {
        return AdminPermissions::PURCHASE_ORDERS_CREATE;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'supplier_id' => ['required', 'uuid', 'exists:suppliers,id'],
            'currency' => ['nullable', 'string', 'size:3'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_variant_id' => ['required', 'uuid', 'exists:product_variants,id'],
            'items.*.quantity_ordered' => ['required', 'integer', 'min:1'],
            'items.*.unit_cost' => ['required', 'numeric', 'min:0'],
            'items.*.currency' => ['nullable', 'string', 'size:3'],
        ];
    }
}
