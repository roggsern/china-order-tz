<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Concerns\AuthorizesAdminPermission;
use App\Support\Admin\AdminPermissions;
use Illuminate\Foundation\Http\FormRequest;

class PreviewNotificationTemplateRequest extends FormRequest
{
    use AuthorizesAdminPermission;

    protected function requiredPermission(): string
    {
        return AdminPermissions::NOTIFICATIONS_TEMPLATES_VIEW;
    }

    public function rules(): array
    {
        return [
            'variables' => ['sometimes', 'array'],
            'variables.*' => ['nullable'],
        ];
    }
}
