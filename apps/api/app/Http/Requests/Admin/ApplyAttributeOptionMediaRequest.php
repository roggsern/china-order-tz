<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Concerns\AuthorizesAdminPermission;
use App\Support\Admin\AdminPermissions;
use App\Support\ProductMedia\ProductMediaUploadContract;
use App\Support\ProductMedia\ProductMediaUploadDiagnostics;
use App\Support\Security\SafePublicUrl;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

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
            'file' => ProductMediaUploadContract::imageFileRules('required_without:url', 'nullable'),
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

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ProductMediaUploadContract::fileMessages('file');
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function () {
            if ($this->hasFile('file')) {
                ProductMediaUploadDiagnostics::logIfEnabled($this->file('file'));
            }
        });
    }
}
