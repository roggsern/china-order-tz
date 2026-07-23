<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Concerns\AuthorizesAdminPermission;
use App\Support\Admin\AdminPermissions;
use Illuminate\Foundation\Http\FormRequest;

class IndexActivityLogsRequest extends FormRequest
{
    use AuthorizesAdminPermission;

    protected function requiredPermission(): string
    {
        return AdminPermissions::ACTIVITY_LOGS_VIEW;
    }

    public function rules(): array
    {
        return [
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'event_type' => ['sometimes', 'string', 'max:100'],
            'actor_type' => ['sometimes', 'string', 'max:50'],
            'actor_id' => ['sometimes', 'uuid'],
            'subject_type' => ['sometimes', 'string', 'max:255'],
            'subject_id' => ['sometimes', 'uuid'],
            'search' => ['sometimes', 'string', 'max:255'],
            'date_from' => ['sometimes', 'date'],
            'date_to' => ['sometimes', 'date', 'after_or_equal:date_from'],
        ];
    }
}
