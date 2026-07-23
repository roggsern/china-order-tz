<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Concerns\AuthorizesAdminPermission;
use App\Support\Admin\AdminPermissions;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCustomerTagRequest extends FormRequest
{
    use AuthorizesAdminPermission;

    protected function requiredPermission(): string
    {
        return AdminPermissions::CUSTOMERS_MANAGE_TAGS;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $tagId = $this->route('tag')?->id ?? $this->route('tag');

        return [
            'name' => ['sometimes', 'string', 'max:100', Rule::unique('customer_tags', 'name')->ignore($tagId)],
            'slug' => ['sometimes', 'nullable', 'string', 'max:120', Rule::unique('customer_tags', 'slug')->ignore($tagId)],
            'description' => ['nullable', 'string', 'max:500'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
