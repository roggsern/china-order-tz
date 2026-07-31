<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Concerns\AuthorizesAdminPermission;
use App\Support\Admin\AdminPermissions;
use Illuminate\Foundation\Http\FormRequest;

class UpdateStoreSettingsRequest extends FormRequest
{
    use AuthorizesAdminPermission;

    protected function requiredPermission(): string
    {
        return AdminPermissions::STORES_MANAGE;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'business' => ['sometimes', 'array'],
            'business.display_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'business.phone' => ['sometimes', 'nullable', 'string', 'max:64'],
            'business.email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'business.address' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'receipt' => ['sometimes', 'array'],
            'receipt.footer_message' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'receipt.show_logo' => ['sometimes', 'boolean'],
            'customer' => ['sometimes', 'array'],
            'customer.support_phone' => ['sometimes', 'nullable', 'string', 'max:64'],
            'customer.support_email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'social' => ['sometimes', 'array'],
            'social.instagram' => ['sometimes', 'nullable', 'string', 'max:255'],
            'social.facebook' => ['sometimes', 'nullable', 'string', 'max:255'],
            'social.tiktok' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }
}
