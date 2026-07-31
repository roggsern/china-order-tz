<?php

namespace App\Http\Requests\Admin;

use App\Enums\ProductMediaType;
use App\Http\Requests\Admin\Concerns\ValidatesProductVariantMediaBinding;
use App\Http\Requests\Concerns\AuthorizesAdminPermission;
use App\Support\Admin\AdminPermissions;
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
            'file' => ['sometimes', 'nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120', 'dimensions:max_width=5000,max_height=5000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $this->validateProductVariantMediaBinding($validator);
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
