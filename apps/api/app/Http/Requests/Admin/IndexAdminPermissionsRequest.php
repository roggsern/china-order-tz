<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Concerns\AuthorizesAdminPermission;
use App\Support\Admin\AdminPermissions;
use Illuminate\Foundation\Http\FormRequest;

class IndexAdminPermissionsRequest extends FormRequest
{
    use AuthorizesAdminPermission;

    protected function requiredPermission(): string
    {
        return AdminPermissions::ROLES_MANAGE_PERMISSIONS;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [];
    }
}
