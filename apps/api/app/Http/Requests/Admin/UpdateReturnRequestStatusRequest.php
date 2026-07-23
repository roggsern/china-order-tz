<?php

namespace App\Http\Requests\Admin;

use App\Enums\InventoryDisposition;
use App\Enums\ReturnRequestStatus;
use App\Http\Requests\Concerns\AuthorizesAdminPermission;
use App\Support\Admin\AdminPermissions;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateReturnRequestStatusRequest extends FormRequest
{
    use AuthorizesAdminPermission;

    protected function requiredPermission(): string
    {
        return AdminPermissions::RETURNS_MANAGE;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(ReturnRequestStatus::class)],
            'admin_notes' => ['nullable', 'string', 'max:2000'],
            'items' => ['sometimes', 'array'],
            'items.*.id' => ['required_with:items', 'uuid'],
            'items.*.condition' => ['nullable', 'string', 'max:100'],
            'items.*.resolution' => ['nullable', 'string', 'max:50'],
            'items.*.refund_amount' => ['nullable', 'numeric', 'min:0'],
            // Explicit allowlist — do not infer sellable from approval alone (RC1-G3).
            'items.*.inventory_disposition' => [
                'nullable',
                'string',
                Rule::in([
                    InventoryDisposition::Sellable->value,
                    InventoryDisposition::Damaged->value,
                    InventoryDisposition::NoRestock->value,
                    InventoryDisposition::InspectionHold->value,
                    InventoryDisposition::Inspection->value,
                ]),
            ],
        ];
    }
}
