<?php

namespace App\Http\Requests\Admin;

use App\Enums\ProductMediaType;
use App\Http\Requests\Admin\Concerns\ValidatesProductVariantMediaBinding;
use App\Http\Requests\Concerns\AuthorizesAdminPermission;
use App\Support\Admin\AdminPermissions;
use App\Support\ProductMedia\ProductMediaUploadContract;
use App\Support\ProductMedia\ProductMediaUploadDiagnostics;
use App\Support\Security\SafePublicUrl;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateProductMediaRequest extends FormRequest
{
    use AuthorizesAdminPermission;
    use ValidatesProductVariantMediaBinding;

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
            'type' => ['sometimes', 'string', Rule::in(array_column(ProductMediaType::cases(), 'value'))],
            'product_variant_id' => ['sometimes', 'nullable', 'uuid'],
            'url' => ['sometimes', 'nullable', 'string', 'max:2048', SafePublicUrl::rule()],
            'thumbnail_url' => ['sometimes', 'nullable', 'string', 'max:2048', SafePublicUrl::rule()],
            'alt_text' => ['sometimes', 'nullable', 'string', 'max:255'],
            'title' => ['sometimes', 'nullable', 'string', 'max:255'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:999999'],
            'is_primary' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
            'file' => ProductMediaUploadContract::imageFileRules('sometimes', 'nullable'),
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
        $this->validateProductVariantMediaBinding($validator);

        $validator->after(function () {
            if ($this->hasFile('file')) {
                ProductMediaUploadDiagnostics::logIfEnabled($this->file('file'));
            }
        });
    }

    protected function prepareForValidation(): void
    {
        foreach (['is_primary', 'is_active'] as $field) {
            if ($this->has($field) && ! is_bool($this->input($field))) {
                $this->merge([
                    $field => filter_var($this->input($field), FILTER_VALIDATE_BOOLEAN),
                ]);
            }
        }

        if ($this->has('product_variant_id') && $this->input('product_variant_id') === '') {
            $this->merge(['product_variant_id' => null]);
        }
    }
}
