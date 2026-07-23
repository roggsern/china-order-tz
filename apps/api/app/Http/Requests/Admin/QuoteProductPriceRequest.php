<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Concerns\AuthorizesAdminPermission;
use App\Support\Admin\AdminPermissions;
use Illuminate\Foundation\Http\FormRequest;

class QuoteProductPriceRequest extends FormRequest
{
    use AuthorizesAdminPermission;

    protected function requiredPermission(): string
    {
        return AdminPermissions::PRICING_VIEW;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'configuration_id' => ['nullable', 'uuid', 'exists:product_variants,id'],
            'quantity' => ['required', 'integer', 'min:1'],
            'promotion_code' => ['nullable', 'string', 'max:100'],
            'coupon_code' => ['nullable', 'string', 'max:100'],
            'customer_group_id' => ['nullable', 'uuid'],
        ];
    }
}
