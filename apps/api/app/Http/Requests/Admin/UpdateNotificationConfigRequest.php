<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Concerns\AuthorizesAdminPermission;
use App\Support\Admin\AdminPermissions;
use Illuminate\Foundation\Http\FormRequest;

class UpdateNotificationConfigRequest extends FormRequest
{
    use AuthorizesAdminPermission;

    protected function requiredPermission(): string
    {
        return AdminPermissions::NOTIFICATIONS_MANAGE;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'channels' => ['sometimes', 'array'],
            'channels.email_enabled' => ['sometimes', 'boolean'],
            'channels.sms_enabled' => ['sometimes', 'boolean'],
            'channels.whatsapp_enabled' => ['sometimes', 'boolean'],
            'channels.push_enabled' => ['sometimes', 'boolean'],
            'channels.in_app_enabled' => ['sometimes', 'boolean'],
            'email_enabled' => ['sometimes', 'boolean'],
            'sms_enabled' => ['sometimes', 'boolean'],
            'whatsapp_enabled' => ['sometimes', 'boolean'],
            'push_enabled' => ['sometimes', 'boolean'],
            'in_app_enabled' => ['sometimes', 'boolean'],
            'event_channel_map' => ['sometimes', 'array'],
            'event_channel_map.*' => ['array'],
            'event_channel_map.*.*' => ['string'],
        ];
    }
}
