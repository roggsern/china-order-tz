<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Concerns\AuthorizesAdminPermission;
use App\Support\Admin\AdminPermissions;
use Illuminate\Foundation\Http\FormRequest;

class ReceivePurchaseOrderRequest extends FormRequest
{
    use AuthorizesAdminPermission;

    protected function requiredPermission(): string
    {
        return AdminPermissions::PURCHASE_ORDERS_RECEIVE;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'notes' => ['nullable', 'string'],
            'store_id' => ['nullable', 'uuid', 'exists:stores,id'],
            'inventory_location_id' => ['nullable', 'uuid', 'exists:inventory_locations,id'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.purchase_order_item_id' => ['required', 'uuid', 'exists:purchase_order_items,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
        ];
    }
}
