<?php

namespace App\Http\Requests\Admin;

use App\Support\Admin\AdminPermissions;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ImportTaxonomyToStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        if (! $user instanceof \App\Models\Admin) {
            return false;
        }

        if (! $user->hasAdminPermission(AdminPermissions::CATALOG_CREATE)) {
            return false;
        }

        $includeProductTypes = $this->boolean('include_product_types', true);
        $includeAttributeMappings = $this->boolean('include_attribute_mappings', true);

        if ($includeProductTypes || $includeAttributeMappings) {
            return $user->hasAdminPermission(AdminPermissions::CONFIGURATION_MANAGE);
        }

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'department_id' => ['required', 'uuid', 'exists:departments,id'],
            'category_ids' => ['required', 'array', 'min:1'],
            'category_ids.*' => ['required', 'uuid', 'distinct'],
            'include_product_types' => ['sometimes', 'boolean'],
            'include_attribute_mappings' => ['sometimes', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            if ($this->boolean('include_attribute_mappings') && ! $this->boolean('include_product_types', true)) {
                // Default include_product_types is true in the action; only fail when
                // explicitly false while mappings are requested.
                if ($this->has('include_product_types') && ! $this->boolean('include_product_types')) {
                    $validator->errors()->add(
                        'include_attribute_mappings',
                        'Attribute mappings require Include Product Types.',
                    );
                }
            }
        });
    }
}
