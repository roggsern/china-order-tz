<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Concerns\AuthorizesAdminPermission;
use App\Support\Admin\AdminPermissions;
use App\Support\Security\SafePublicUrl;
use Illuminate\Foundation\Http\FormRequest;

class ApplyAttributeOptionMediaRequest extends FormRequest
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
            'catalog_attribute_option_id' => ['required', 'uuid', 'exists:catalog_attribute_options,id'],
            'file' => [
                'required_without:url',
                'nullable',
                'file',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:5120',
                'dimensions:max_width=5000,max_height=5000',
            ],
            'url' => [
                'required_without:file',
                'nullable',
                'string',
                'max:2048',
                SafePublicUrl::rule(),
            ],
            'alt_text' => ['sometimes', 'nullable', 'string', 'max:255'],
            'title' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }
}
