<?php

namespace App\Http\Requests\Admin;

use App\Enums\PromotionDiscountType;
use App\Enums\PromotionRuleType;
use App\Enums\PromotionType;
use App\Http\Requests\Concerns\AuthorizesAdminPermission;
use App\Support\Admin\AdminPermissions;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePromotionRequest extends FormRequest
{
    use AuthorizesAdminPermission;

    protected function requiredPermission(): string
    {
        return AdminPermissions::PROMOTIONS_UPDATE;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $id = $this->route('promotion')?->id ?? $this->route('promotion');

        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'code' => ['sometimes', 'nullable', 'string', 'max:64', Rule::unique('promotions', 'code')->ignore($id)],
            'type' => ['sometimes', Rule::enum(PromotionType::class)],
            'discount_type' => ['sometimes', Rule::enum(PromotionDiscountType::class)],
            'value' => ['sometimes', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date'],
            'usage_limit' => ['nullable', 'integer', 'min:1'],
            'per_customer_limit' => ['nullable', 'integer', 'min:1'],
            'minimum_order_amount' => ['nullable', 'numeric', 'min:0'],
            'rules' => ['sometimes', 'array'],
            'rules.*.rule_type' => ['required_with:rules', Rule::enum(PromotionRuleType::class)],
            'rules.*.rule_value' => ['required_with:rules', 'array'],
        ];
    }
}
