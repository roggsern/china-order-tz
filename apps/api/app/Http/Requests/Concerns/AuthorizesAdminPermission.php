<?php

namespace App\Http\Requests\Concerns;

use App\Models\Admin;
use App\Support\Admin\AdminPermissions;

trait AuthorizesAdminPermission
{
    /**
     * Single permission slug, or list (ANY match grants access).
     *
     * @return string|list<string>
     */
    abstract protected function requiredPermission(): string|array;

    public function authorize(): bool
    {
        $user = $this->user();

        if (! $user instanceof Admin) {
            return false;
        }

        $required = $this->requiredPermission();
        $permissions = is_array($required) ? $required : [$required];

        foreach ($permissions as $permission) {
            if (! is_string($permission) || ! AdminPermissions::isKnown($permission)) {
                continue;
            }

            if ($user->hasAdminPermission($permission)) {
                return true;
            }
        }

        return false;
    }
}
