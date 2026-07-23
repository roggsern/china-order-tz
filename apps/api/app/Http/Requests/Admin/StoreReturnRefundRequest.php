<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Concerns\AuthorizesAdminPermission;
use App\Support\Admin\AdminPermissions;
use Illuminate\Foundation\Http\FormRequest;

class StoreReturnRefundRequest extends FormRequest
{
    use AuthorizesAdminPermission;

    protected function requiredPermission(): string
    {
        return AdminPermissions::RETURNS_REFUND;
    }

    public function rules(): array
    {
        return [
            'amount' => ['sometimes', 'numeric', 'min:0.01'],
            'currency' => ['sometimes', 'string', 'size:3'],
            'method' => ['sometimes', 'string', 'max:50'],
            'reference' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
            // Optional immediate status advance after create (still manual â€” not auto-complete).
            'status' => ['sometimes', 'string', 'in:pending,approved,processing,completed,failed'],
        ];
    }
}
