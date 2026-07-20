<?php

namespace App\Http\Requests\Admin;

use App\Enums\NotificationChannel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateNotificationTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        /** @var \App\Models\NotificationTemplate $template */
        $template = $this->route('template');

        return [
            'key' => [
                'sometimes',
                'string',
                'max:150',
                Rule::unique('notification_templates', 'key')->ignore($template?->id),
            ],
            'name' => ['sometimes', 'string', 'max:255'],
            'channel' => ['sometimes', Rule::enum(NotificationChannel::class)],
            'subject' => ['nullable', 'string', 'max:500'],
            'body' => ['sometimes', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
