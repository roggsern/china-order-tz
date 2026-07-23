<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Concerns\AuthorizesAdminPermission;
use App\Support\Admin\AdminPermissions;
use Illuminate\Foundation\Http\FormRequest;

class IndexAdminNotificationsRequest extends FormRequest
{
    use AuthorizesAdminPermission;

    protected function requiredPermission(): string
    {
        return AdminPermissions::NOTIFICATIONS_TEMPLATES_VIEW;
    }

    public function rules(): array
    {
        return [
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'channel' => ['sometimes', 'string', 'max:50'],
            'status' => ['sometimes', 'string', 'max:50'],
            'event_type' => ['sometimes', 'string', 'max:100'],
            'customer_id' => ['sometimes', 'uuid'],
        ];
    }
}
