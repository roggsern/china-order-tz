<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Concerns\AuthorizesAdminPermission;
use App\Support\Admin\AdminPermissions;
use Illuminate\Foundation\Http\FormRequest;

class StoreCustomerTagRequest extends FormRequest
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
        return [
            'name' => ['required', 'string', 'max:100', 'unique:customer_tags,name'],
            'slug' => ['nullable', 'string', 'max:120', 'unique:customer_tags,slug'],
            'description' => ['nullable', 'string', 'max:500'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
