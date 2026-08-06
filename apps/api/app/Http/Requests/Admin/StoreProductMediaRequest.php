<?php

namespace App\Http\Requests\Admin;

use App\Enums\ProductMediaType;
use App\Http\Requests\Admin\Concerns\ValidatesProductVariantMediaBinding;
use App\Http\Requests\Concerns\AuthorizesAdminPermission;
use App\Support\Admin\AdminPermissions;
use App\Support\ProductMedia\ProductMediaUploadContract;
use App\Support\Security\SafePublicUrl;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreProductMediaRequest extends FormRequest
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
        $type = ProductMediaType::tryFromMixed($this->input('type')) ?? ProductMediaType::Image;

        $rules = [
            'type' => ['required', 'string', Rule::in(array_column(ProductMediaType::cases(), 'value'))],
            'product_variant_id' => ['sometimes', 'nullable', 'uuid'],
            'alt_text' => ['sometimes', 'nullable', 'string', 'max:255'],
            'title' => ['sometimes', 'nullable', 'string', 'max:255'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:999999'],
            'is_primary' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
            'thumbnail_url' => ['sometimes', 'nullable', 'string', 'max:2048', SafePublicUrl::rule()],
        ];

        if ($type === ProductMediaType::Video) {
            $rules['url'] = ['required', 'string', 'max:2048', 'url:http,https'];
            $rules['file'] = ['prohibited'];
        } else {
            $rules['file'] = ProductMediaUploadContract::imageFileRules('required_without:url', 'nullable');
            $rules['url'] = [
                'required_without:file',
                'nullable',
                'string',
                'max:2048',
                SafePublicUrl::rule(),
            ];
        }

        return $rules;
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
    }

    protected function prepareForValidation(): void
    {
        if (! $this->filled('type')) {
            $this->merge(['type' => $this->hasFile('file') ? 'image' : 'video']);
        }

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
