<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Concerns\AuthorizesAdminPermission;
use App\Support\Admin\AdminPermissions;
use Illuminate\Foundation\Http\FormRequest;

class AssignWarehousePackerRequest extends FormRequest
{
    use AuthorizesAdminPermission;

    protected function requiredPermission(): string
    {
        return AdminPermissions::WAREHOUSE_JOBS_UPDATE;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'packer_id' => ['nullable', 'uuid', 'exists:admins,id'],
        ];
    }
}
