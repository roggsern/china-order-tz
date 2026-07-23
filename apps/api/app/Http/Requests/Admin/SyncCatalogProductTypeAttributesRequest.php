<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Concerns\AuthorizesAdminPermission;
use App\Support\Admin\AdminPermissions;
use Illuminate\Foundation\Http\FormRequest;

class SyncCatalogProductTypeAttributesRequest extends FormRequest
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
            // Key must be present; empty array is valid and clears all mappings.
            // Use present (not required) so [] is not treated as "missing".
            'attributes' => ['present', 'array'],
            'attributes.*.catalog_attribute_id' => ['required', 'uuid', 'exists:catalog_attributes,id'],
            // Strict boolean â€” no filter_var coercion.
            'attributes.*.is_required' => ['sometimes', 'boolean'],
            'attributes.*.sort_order' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
