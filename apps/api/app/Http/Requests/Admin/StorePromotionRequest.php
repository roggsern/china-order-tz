<?php

namespace App\Http\Requests\Admin;

use App\Enums\PromotionDiscountType;
use App\Enums\PromotionRuleType;
use App\Enums\PromotionStatus;
use App\Enums\PromotionType;
use App\Http\Requests\Concerns\AuthorizesAdminPermission;
use App\Support\Admin\AdminPermissions;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePromotionRequest extends FormRequest
{
    use AuthorizesAdminPermission;

    protected function requiredPermission(): string
    {
        return AdminPermissions::PROMOTIONS_CREATE;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => [
                Rule::requiredIf(fn () => $this->input('type') === PromotionType::Coupon->value),
                'nullable',
                'string',
                'max:64',
                'unique:promotions,code',
            ],
            'type' => ['required', Rule::enum(PromotionType::class)],
            'discount_type' => ['required', Rule::enum(PromotionDiscountType::class)],
            'value' => ['required', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3'],
            'status' => ['sometimes', Rule::enum(PromotionStatus::class)],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'usage_limit' => ['nullable', 'integer', 'min:1'],
            'per_customer_limit' => ['nullable', 'integer', 'min:1'],
            'minimum_order_amount' => ['nullable', 'numeric', 'min:0'],
            'rules' => ['sometimes', 'array'],
            'rules.*.rule_type' => ['required_with:rules', Rule::enum(PromotionRuleType::class)],
            'rules.*.rule_value' => ['required_with:rules', 'array'],
        ];
    }
}
