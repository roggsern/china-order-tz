<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Concerns\AuthorizesAdminPermission;
use App\Support\Admin\AdminPermissions;
use Illuminate\Foundation\Http\FormRequest;

class GenerateProductVariantsRequest extends FormRequest
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
            'attributes' => ['required', 'array', 'min:1'],
            'attributes.*.catalog_attribute_id' => ['required', 'uuid', 'exists:catalog_attributes,id'],
            'attributes.*.option_ids' => ['required', 'array', 'min:1'],
            'attributes.*.option_ids.*' => ['uuid', 'exists:catalog_attribute_options,id'],
            'replace_existing' => ['sometimes', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('replace_existing') && ! is_bool($this->input('replace_existing'))) {
            $this->merge([
                'replace_existing' => filter_var($this->input('replace_existing'), FILTER_VALIDATE_BOOLEAN),
            ]);
        }
    }
}
