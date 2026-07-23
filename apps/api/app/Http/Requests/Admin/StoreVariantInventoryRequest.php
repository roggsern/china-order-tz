<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Concerns\AuthorizesAdminPermission;
use App\Support\Admin\AdminPermissions;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreVariantInventoryRequest extends FormRequest
{
    use AuthorizesAdminPermission;

    protected function requiredPermission(): string
    {
        return AdminPermissions::INVENTORY_RECEIVE;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $variantId = $this->route('variant')?->id ?? $this->route('variant');

        return [
            'warehouse_code' => [
                'sometimes',
                'string',
                'max:32',
                Rule::unique('variant_inventories', 'warehouse_code')
                    ->where(fn ($query) => $query
                        ->where('product_variant_id', $variantId)
                        ->whereNull('deleted_at')),
            ],
            'on_hand' => ['sometimes', 'integer', 'min:0'],
            'reserved' => ['sometimes', 'integer', 'min:0'],
            'reorder_level' => ['sometimes', 'integer', 'min:0'],
            'safety_stock' => ['sometimes', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
            'idempotency_key' => ['sometimes', 'nullable', 'string', 'max:191'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // When on_hand is omitted, opening qty may inherit from legacy (RC1-B1).
            // Only enforce reserved â‰¤ on_hand when the client supplies on_hand explicitly.
            if (! $this->exists('on_hand')) {
                return;
            }

            $onHand = (int) $this->input('on_hand', 0);
            $reserved = (int) ($this->input('reserved', 0));

            if ($reserved > $onHand) {
                $validator->errors()->add('reserved', 'Reserved cannot exceed on hand.');
            }
        });
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('warehouse_code') && is_string($this->input('warehouse_code'))) {
            $this->merge(['warehouse_code' => strtoupper(trim($this->input('warehouse_code')))]);
        }

        if ($this->has('is_active') && ! is_bool($this->input('is_active'))) {
            $this->merge([
                'is_active' => filter_var($this->input('is_active'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE),
            ]);
        }
    }
}
