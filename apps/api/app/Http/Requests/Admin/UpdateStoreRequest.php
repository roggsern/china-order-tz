<?php

namespace App\Http\Requests\Admin;

use App\Models\Store;
use App\Support\Admin\AdminPermissions;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(AdminPermissions::STORES_UPDATE) ?? false;
    }

    public function rules(): array
    {
        /** @var Store $store */
        $store = $this->route('store');

        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'slug' => ['sometimes', 'string', 'max:255', Rule::unique('stores', 'slug')->ignore($store->id)],
            'description' => ['nullable', 'string'],
            'theme_color' => ['nullable', 'string', 'max:32'],
            'is_active' => ['sometimes', 'boolean'],
            'storefront_enabled' => ['sometimes', 'boolean'],
            'storefront_visible' => ['sometimes', 'boolean'],
            'storefront_featured' => ['sometimes', 'boolean'],
            'storefront_sort_order' => ['nullable', 'integer', 'min:0'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'code' => ['prohibited'],
            'logo_path' => ['prohibited'],
            'banner_path' => ['prohibited'],
            'settings' => ['prohibited'],
        ];
    }
}
