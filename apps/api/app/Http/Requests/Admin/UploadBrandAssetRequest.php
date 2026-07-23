<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Concerns\AuthorizesAdminPermission;
use App\Support\Admin\AdminPermissions;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UploadBrandAssetRequest extends FormRequest
{
    use AuthorizesAdminPermission;

    protected function requiredPermission(): string
    {
        return AdminPermissions::CATALOG_UPDATE;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'field' => ['required', 'string', Rule::in(['logo', 'banner'])],
            'file' => ['required', 'file', 'image', 'max:5120', 'mimes:jpg,jpeg,png,webp', 'dimensions:max_width=5000,max_height=5000'],
        ];
    }
}
