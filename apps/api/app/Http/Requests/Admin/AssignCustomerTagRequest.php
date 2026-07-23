<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Concerns\AuthorizesAdminPermission;
use App\Support\Admin\AdminPermissions;
use Illuminate\Foundation\Http\FormRequest;

class AssignCustomerTagRequest extends FormRequest
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
            'tag_id' => ['required', 'uuid', 'exists:customer_tags,id'],
        ];
    }
}
