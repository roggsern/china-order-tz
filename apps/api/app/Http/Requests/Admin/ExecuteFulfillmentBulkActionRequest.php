<?php

namespace App\Http\Requests\Admin;

use App\Models\Admin;
use App\Support\Admin\AdminPermissions;
use Illuminate\Foundation\Http\FormRequest;

class ExecuteFulfillmentBulkActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if (! $user instanceof Admin) {
            return false;
        }

        $actionKey = strtoupper(trim((string) $this->input('action_key', '')));

        return match ($actionKey) {
            'CREATE_SUPPLIER_PURCHASE' => $user->hasAdminPermission(AdminPermissions::PROCUREMENT_CREATE),
            'RECEIVE_GOODS' => $user->hasAdminPermission(AdminPermissions::PURCHASE_ORDERS_RECEIVE),
            'MARK_QC_PASSED' => $user->hasAdminPermission(AdminPermissions::PROCUREMENT_UPDATE),
            'MARK_CHINA_PACKING_COMPLETE' => $user->hasAdminPermission(AdminPermissions::WAREHOUSE_JOBS_UPDATE)
                && $user->hasAdminPermission(AdminPermissions::WAREHOUSE_JOBS_COMPLETE),
            'MARK_EXPORT_READY' => $user->hasAdminPermission(AdminPermissions::WAREHOUSE_JOBS_COMPLETE),
            'MARK_AGENT_DELIVERED' => $user->hasAdminPermission(AdminPermissions::ORDERS_SHIP),
            'MARK_CUSTOMER_COLLECTED' => $user->hasAdminPermission(AdminPermissions::ORDERS_FULFILL),
            'MARK_CUSTOMER_DELIVERED' => $user->hasAdminPermission(AdminPermissions::ORDERS_FULFILL),
            'CREATE_SHIPMENT' => $user->hasAdminPermission(AdminPermissions::ORDERS_SHIP),
            'MARK_LOCAL_ORDER_COMPLETED' => $user->hasAdminPermission(AdminPermissions::ORDERS_FULFILL),
            'MARK_LOCAL_ORDER_READY' => $user->hasAdminPermission(AdminPermissions::WAREHOUSE_JOBS_UPDATE)
                && $user->hasAdminPermission(AdminPermissions::WAREHOUSE_JOBS_COMPLETE),
            default => false,
        };
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'action_key' => ['required', 'string', 'max:64'],
            'fulfillment_ids' => ['required', 'array', 'min:1', 'max:500'],
            'fulfillment_ids.*' => ['required', 'uuid'],
        ];
    }
}
