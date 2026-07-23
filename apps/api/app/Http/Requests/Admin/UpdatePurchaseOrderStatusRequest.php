<?php

namespace App\Http\Requests\Admin;

use App\Enums\PurchaseOrderStatus;
use App\Models\Admin;
use App\Support\Admin\AdminPermissions;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePurchaseOrderStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        if (! $user instanceof Admin) {
            return false;
        }
        $status = (string) $this->input('status');
        $permission = match ($status) {
            PurchaseOrderStatus::Cancelled->value => AdminPermissions::PURCHASE_ORDERS_CANCEL,
            PurchaseOrderStatus::Sent->value, PurchaseOrderStatus::Confirmed->value => AdminPermissions::PURCHASE_ORDERS_APPROVE,
            default => AdminPermissions::PURCHASE_ORDERS_UPDATE,
        };

        return $user->hasAdminPermission($permission);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'status' => ['required', 'string', Rule::enum(PurchaseOrderStatus::class)],
            'notes' => ['nullable', 'string'],
        ];
    }
}
