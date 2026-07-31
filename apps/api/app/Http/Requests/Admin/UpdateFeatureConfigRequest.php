<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Concerns\AuthorizesAdminPermission;
use App\Support\Admin\AdminPermissions;
use Illuminate\Foundation\Http\FormRequest;

class UpdateFeatureConfigRequest extends FormRequest
{
    use AuthorizesAdminPermission;

    protected function requiredPermission(): string
    {
        return AdminPermissions::FEATURES_MANAGE;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'maintenance_mode' => ['sometimes', 'boolean'],
            'maintenance_message' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'flags' => ['sometimes', 'array'],
            'flags.wishlist' => ['sometimes', 'boolean'],
            'flags.reviews' => ['sometimes', 'boolean'],
            'flags.new_checkout' => ['sometimes', 'boolean'],
        ];
    }
}
