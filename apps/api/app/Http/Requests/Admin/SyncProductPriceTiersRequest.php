<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Concerns\AuthorizesAdminPermission;
use App\Support\Admin\AdminPermissions;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SyncProductPriceTiersRequest extends FormRequest
{
    use AuthorizesAdminPermission;

    protected function requiredPermission(): string
    {
        return AdminPermissions::PRICING_MANAGE;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'configuration_id' => ['nullable', 'uuid', 'exists:product_variants,id'],
            'price_tiers' => ['required', 'array'],
            'price_tiers.*.min_quantity' => ['required', 'integer', 'min:1'],
            'price_tiers.*.tier_type' => ['sometimes', 'string', Rule::in(['fixed_unit', 'percent_off'])],
            'price_tiers.*.unit_price' => ['nullable', 'numeric', 'min:0'],
            'price_tiers.*.discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ];
    }
}
