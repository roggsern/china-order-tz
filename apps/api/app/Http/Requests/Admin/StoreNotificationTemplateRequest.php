<?php

namespace App\Http\Requests\Admin;

use App\Enums\NotificationChannel;
use App\Http\Requests\Concerns\AuthorizesAdminPermission;
use App\Support\Admin\AdminPermissions;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreNotificationTemplateRequest extends FormRequest
{
    use AuthorizesAdminPermission;

    protected function requiredPermission(): string
    {
        return AdminPermissions::NOTIFICATIONS_TEMPLATES_MANAGE;
    }

    public function rules(): array
    {
        return [
            'key' => ['required', 'string', 'max:150', 'unique:notification_templates,key'],
            'name' => ['required', 'string', 'max:255'],
            'channel' => ['required', Rule::enum(NotificationChannel::class)],
            'subject' => ['nullable', 'string', 'max:500'],
            'body' => ['required', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
