<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Concerns\AuthorizesAdminPermission;
use App\Support\Admin\AdminPermissions;
use App\Enums\CatalogOrigin;
use App\Support\Catalog\CategoryRelationshipRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateCategoryRequest extends FormRequest
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
        /** @var \App\Models\Category $category */
        $category = $this->route('category');

        return [
            'name' => ['required', 'string', 'max:255'],
            'department_id' => ['required', 'uuid', 'exists:departments,id'],
            'slug' => [
                'sometimes',
                'nullable',
                'string',
                'max:255',
                Rule::unique('categories', 'slug')->ignore($category),
            ],
            'parent_id' => [
                'sometimes',
                'nullable',
                'uuid',
                'exists:categories,id',
                Rule::notIn([$category->id]),
            ],
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

            /** @var \App\Models\Category $category */
            $category = $this->route('category');
            $origin = $this->input('origin');

            if (! is_string($origin) || $origin === '') {
                return;
            }

            $storeId = $this->has('store_id')
                ? $this->input('store_id')
                : ($origin === CatalogOrigin::China->value ? null : $category->store_id);

            try {
                CategoryRelationshipRules::assertOriginStoreConsistency($origin, $storeId);

                $parentId = $this->has('parent_id')
                    ? $this->input('parent_id')
                    : $category->parent_id;

                CategoryRelationshipRules::assertParentRelationship(
                    $parentId,
                    (string) $this->input('department_id'),
                    (string) $category->id,
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
