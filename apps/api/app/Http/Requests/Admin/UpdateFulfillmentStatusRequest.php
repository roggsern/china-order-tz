<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Concerns\AuthorizesAdminPermission;
use App\Support\Admin\AdminPermissions;
use App\Enums\FulfillmentStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateFulfillmentStatusRequest extends FormRequest
{
    use AuthorizesAdminPermission;

    protected function requiredPermission(): string
    {
        return AdminPermissions::ORDERS_FULFILL;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => ['sometimes', 'required', Rule::enum(FulfillmentStatus::class)],
            'assigned_to' => ['sometimes', 'nullable', 'uuid', 'exists:admins,id'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ];
    }
}
