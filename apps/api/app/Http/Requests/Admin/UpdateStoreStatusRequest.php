<?php

namespace App\Http\Requests\Admin;

use App\Support\Admin\AdminPermissions;
use Illuminate\Foundation\Http\FormRequest;

class UpdateStoreStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(AdminPermissions::STORES_UPDATE) ?? false;
    }

    public function rules(): array
    {
        return [
            'is_active' => ['required', 'boolean'],
        ];
    }
}
