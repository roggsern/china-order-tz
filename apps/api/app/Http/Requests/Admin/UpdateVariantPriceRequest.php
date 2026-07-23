<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Concerns\AuthorizesAdminPermission;
use App\Support\Admin\AdminPermissions;
use App\Enums\VariantPriceType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateVariantPriceRequest extends FormRequest
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
            'price_type' => ['sometimes', Rule::in(VariantPriceType::values())],
            'currency' => ['sometimes', 'string', 'size:3', 'regex:/^[A-Za-z]{3}$/'],
            'amount' => ['sometimes', 'numeric', 'min:0'],
            'compare_at_price' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'cost_price' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'minimum_quantity' => ['sometimes', 'integer', 'min:1'],
            'is_active' => ['sometimes', 'boolean'],
            'starts_at' => ['sometimes', 'nullable', 'date'],
            'ends_at' => ['sometimes', 'nullable', 'date', 'after_or_equal:starts_at'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('currency') && is_string($this->input('currency'))) {
            $this->merge(['currency' => strtoupper(trim($this->input('currency')))]);
        }

        if ($this->has('price_type') && is_string($this->input('price_type'))) {
            $this->merge(['price_type' => strtolower(trim($this->input('price_type')))]);
        }

        if ($this->has('is_active') && ! is_bool($this->input('is_active'))) {
            $this->merge([
                'is_active' => filter_var($this->input('is_active'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE),
            ]);
        }
    }
}
