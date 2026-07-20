<?php

namespace App\Http\Requests\Admin;

use App\Enums\NotificationChannel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreNotificationTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
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
