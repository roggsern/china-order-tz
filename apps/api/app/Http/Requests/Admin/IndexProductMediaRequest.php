<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Admin\Concerns\ValidatesProductVariantMediaBinding;
use App\Http\Requests\Concerns\AuthorizesAdminPermission;
use App\Support\Admin\AdminPermissions;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class IndexProductMediaRequest extends FormRequest
{
    use AuthorizesAdminPermission;
    use ValidatesProductVariantMediaBinding;

    protected function requiredPermission(): string
    {
        return AdminPermissions::CATALOG_VIEW;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'product_variant_id' => ['sometimes', 'nullable', 'uuid'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $this->validateProductVariantMediaBinding($validator);
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('product_variant_id') && $this->input('product_variant_id') === '') {
            $this->merge(['product_variant_id' => null]);
        }
    }
}
