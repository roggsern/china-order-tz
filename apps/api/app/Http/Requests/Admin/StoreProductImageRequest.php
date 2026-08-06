<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Concerns\AuthorizesAdminPermission;
use App\Support\Admin\AdminPermissions;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Legacy product image upload (POST /products/{id}/images).
 *
 * Not the live catalog product/variant media UI contract (10 MB via product_media).
 * Kept at 2 MB intentionally — do not raise without migrating callers off this path.
 */
class StoreProductImageRequest extends FormRequest
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
        return [
            'image' => ['required', 'image', 'max:2048', 'mimes:jpg,jpeg,png,webp', 'dimensions:max_width=5000,max_height=5000'],
        ];
    }
}
