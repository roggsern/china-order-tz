<?php

namespace App\Http\Requests\Admin;

use App\Support\Admin\AdminPermissions;
use Illuminate\Foundation\Http\FormRequest;

class StoreStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(AdminPermissions::STORES_CREATE) ?? false;
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:32', 'unique:stores,code'],
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'unique:stores,slug'],
            'description' => ['nullable', 'string'],
            'theme_color' => ['nullable', 'string', 'max:32'],
            'is_active' => ['sometimes', 'boolean'],
            'storefront_enabled' => ['sometimes', 'boolean'],
            'storefront_visible' => ['sometimes', 'boolean'],
            'storefront_featured' => ['sometimes', 'boolean'],
            'storefront_sort_order' => ['nullable', 'integer', 'min:0'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'logo_path' => ['prohibited'],
            'banner_path' => ['prohibited'],
            'settings' => ['prohibited'],
        ];
    }
}
