<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Concerns\AuthorizesAdminPermission;
use App\Support\Admin\AdminPermissions;
use Illuminate\Foundation\Http\FormRequest;

class IndexAdminRolesRequest extends FormRequest
{
    use AuthorizesAdminPermission;

    protected function requiredPermission(): string
    {
        return AdminPermissions::ADMINS_VIEW;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'assignable' => ['sometimes', 'boolean'],
        ];
    }
}
