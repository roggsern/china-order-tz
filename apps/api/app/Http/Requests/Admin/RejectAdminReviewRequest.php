<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Concerns\AuthorizesAdminPermission;
use App\Support\Admin\AdminPermissions;
use Illuminate\Foundation\Http\FormRequest;

class RejectAdminReviewRequest extends FormRequest
{
    use AuthorizesAdminPermission;

    protected function requiredPermission(): string
    {
        return AdminPermissions::REVIEWS_MANAGE;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'moderation_note' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ];
    }
}
