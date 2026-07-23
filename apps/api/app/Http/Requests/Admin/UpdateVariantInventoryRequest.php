<?php

namespace App\Http\Requests\Admin;

use App\Models\Admin;
use App\Support\Admin\AdminPermissions;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateVariantInventoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        if (! $user instanceof Admin) {
            return false;
        }

        if ($this->filled('reserve')) {
            return $user->hasAdminPermission(AdminPermissions::INVENTORY_RESERVE);
        }

        if ($this->filled('release')) {
            return $user->hasAdminPermission(AdminPermissions::INVENTORY_RELEASE);
        }

        if ($this->exists('on_hand') || $this->exists('reserved')) {
            return $user->hasAdminPermission(AdminPermissions::INVENTORY_ADJUST);
        }

        // Metadata (reorder_level, warehouse_code, is_active, …)
        return $user->hasAdminPermission(AdminPermissions::INVENTORY_ADJUST)
            || $user->hasAdminPermission(AdminPermissions::INVENTORY_VIEW);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var \App\Models\VariantInventory|null $inventory */
        $inventory = $this->route('inventory');

        return [
            'warehouse_code' => [
                'sometimes',
                'string',
                'max:32',
                Rule::unique('variant_inventories', 'warehouse_code')
                    ->ignore($inventory?->id)
                    ->where(fn ($query) => $query
                        ->where('product_variant_id', $inventory?->product_variant_id)
                        ->whereNull('deleted_at')),
            ],
            'on_hand' => ['sometimes', 'integer', 'min:0'],
            'reserved' => ['sometimes', 'integer', 'min:0'],
            'reorder_level' => ['sometimes', 'integer', 'min:0'],
            'safety_stock' => ['sometimes', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
            // Convenience mutations (optional).
            'reserve' => ['sometimes', 'integer', 'min:1'],
            'release' => ['sometimes', 'integer', 'min:1'],
            'idempotency_key' => ['sometimes', 'nullable', 'string', 'max:191'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            /** @var \App\Models\VariantInventory|null $inventory */
            $inventory = $this->route('inventory');
            if ($inventory === null) {
                return;
            }

            $onHand = (int) ($this->input('on_hand', $inventory->on_hand));
            $reserved = (int) ($this->input('reserved', $inventory->reserved));

            if ($this->filled('reserve')) {
                $reserved = $reserved + (int) $this->input('reserve');
            }

            if ($this->filled('release')) {
                $reserved = max(0, $reserved - (int) $this->input('release'));
            }

            if ($reserved > $onHand) {
                $validator->errors()->add(
                    $this->filled('reserve') ? 'reserve' : 'reserved',
                    'Reserved cannot exceed on hand.',
                );
            }

            if ($this->filled('release') && (int) $this->input('release') > (int) $inventory->reserved) {
                $validator->errors()->add('release', 'Cannot release more than currently reserved.');
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
