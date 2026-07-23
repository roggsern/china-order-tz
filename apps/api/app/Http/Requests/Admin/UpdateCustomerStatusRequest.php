<?php

namespace App\Http\Requests\Admin;

use App\Enums\CustomerLifecycleStatus;
use App\Http\Requests\Concerns\AuthorizesAdminPermission;
use App\Support\Admin\AdminPermissions;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCustomerStatusRequest extends FormRequest
{
    use AuthorizesAdminPermission;

    protected function requiredPermission(): string
    {
        $status = $this->input('lifecycle_status');

        if ($status === CustomerLifecycleStatus::Blocked->value) {
            return AdminPermissions::CUSTOMERS_BLOCK;
        }

        return AdminPermissions::CUSTOMERS_UPDATE;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'lifecycle_status' => ['required', Rule::enum(CustomerLifecycleStatus::class)],
            'block_reason' => [
                'nullable',
                'string',
                'max:1000',
                Rule::requiredIf(fn () => $this->input('lifecycle_status') === CustomerLifecycleStatus::Blocked->value),
            ],
        ];
    }
}
