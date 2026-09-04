<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Concerns\AuthorizesAdminPermission;
use App\Rules\EligibleFulfillmentAssignee;
use App\Support\Admin\AdminPermissions;
use Illuminate\Foundation\Http\FormRequest;

class UpdateFulfillmentAssignmentRequest extends FormRequest
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
            'assigned_to' => [
                'present',
                'nullable',
                'uuid',
                new EligibleFulfillmentAssignee,
            ],
        ];
    }
}
