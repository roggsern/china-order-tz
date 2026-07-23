<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Concerns\AuthorizesAdminPermission;
use App\Support\Admin\AdminPermissions;
use App\Support\Catalog\CatalogLeafCategoryRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreCatalogProductTypeRequest extends FormRequest
{
    use AuthorizesAdminPermission;

    protected function requiredPermission(): string
    {
        return AdminPermissions::CONFIGURATION_MANAGE;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'subcategory_id' => [
                'required',
                'uuid',
                Rule::exists('categories', 'id')->whereNull('deleted_at'),
            ],
            'slug' => ['sometimes', 'nullable', 'string', 'max:255', 'unique:catalog_product_types,slug'],
            'image' => ['sometimes', 'nullable', 'string', 'max:2048'],
            'description' => ['sometimes', 'nullable', 'string'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            // Strict boolean: true/false/0/1/"0"/"1" only â€” no filter_var coercion.
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $subcategoryId = $this->input('subcategory_id');
            if (! is_string($subcategoryId) || $subcategoryId === '') {
                return;
            }

            try {
                CatalogLeafCategoryRules::assertValidLeafParent($subcategoryId);
            } catch (\Illuminate\Validation\ValidationException $e) {
                foreach ($e->errors() as $field => $messages) {
                    foreach ($messages as $message) {
                        $validator->errors()->add($field, $message);
                    }
                }
            }
        });
    }
}
