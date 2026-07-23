<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Concerns\AuthorizesAdminPermission;
use App\Support\Admin\AdminPermissions;
use App\Enums\CatalogOrigin;
use App\Support\Catalog\CategoryRelationshipRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreCategoryRequest extends FormRequest
{
    use AuthorizesAdminPermission;

    protected function requiredPermission(): string
    {
        return AdminPermissions::CATALOG_CREATE;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'department_id' => ['required', 'uuid', 'exists:departments,id'],
            'slug' => ['sometimes', 'nullable', 'string', 'max:255', 'unique:categories,slug'],
            'parent_id' => ['sometimes', 'nullable', 'uuid', 'exists:categories,id'],
            'origin' => ['required', Rule::enum(CatalogOrigin::class)],
            'store_id' => ['sometimes', 'nullable', 'uuid', 'exists:stores,id'],
            'product_type_id' => [
                'sometimes',
                'nullable',
                'uuid',
                Rule::exists('product_types', 'id')
                    ->whereNull('deleted_at')
                    ->where('is_active', true),
            ],
            'image' => ['sometimes', 'nullable', 'string', 'max:2048'],
            'description' => ['sometimes', 'nullable', 'string'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            // Laravel boolean accepts only: true, false, 0, 1, "0", "1"
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $origin = $this->input('origin');
            if (! is_string($origin) || $origin === '') {
                return;
            }

            try {
                CategoryRelationshipRules::assertOriginStoreConsistency(
                    $origin,
                    $this->input('store_id'),
                );
                CategoryRelationshipRules::assertParentRelationship(
                    $this->input('parent_id'),
                    (string) $this->input('department_id'),
                );
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
