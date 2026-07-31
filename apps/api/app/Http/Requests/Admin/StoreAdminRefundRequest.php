<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Concerns\AuthorizesAdminPermission;
use App\Support\Admin\AdminPermissions;
use Illuminate\Foundation\Http\FormRequest;

class StoreAdminRefundRequest extends FormRequest
{
    use AuthorizesAdminPermission;

    protected function requiredPermission(): string
    {
        return AdminPermissions::REFUNDS_MANAGE;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'order_id' => ['required', 'uuid', 'exists:orders,id'],
            'payment_id' => ['sometimes', 'nullable', 'uuid', 'exists:payments,id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'currency' => ['sometimes', 'nullable', 'string', 'size:3'],
            'reason' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'method' => ['sometimes', 'nullable', 'string', 'max:64'],
            'return_request_id' => ['sometimes', 'nullable', 'uuid', 'exists:return_requests,id'],
        ];
    }
}
