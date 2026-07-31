<?php

namespace App\Http\Requests\Admin;

use App\Support\Admin\AdminPermissions;
use Illuminate\Foundation\Http\FormRequest;

class UploadStoreBrandingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(AdminPermissions::STORES_UPDATE) ?? false;
    }

    public function rules(): array
    {
        return [
            'logo' => [
                'nullable',
                'file',
                'image',
                'max:5120',
                'mimes:jpg,jpeg,png,webp',
                'dimensions:max_width=5000,max_height=5000',
            ],
            'banner' => [
                'nullable',
                'file',
                'image',
                'max:5120',
                'mimes:jpg,jpeg,png,webp',
                'dimensions:max_width=5000,max_height=5000',
            ],
        ];
    }
}
