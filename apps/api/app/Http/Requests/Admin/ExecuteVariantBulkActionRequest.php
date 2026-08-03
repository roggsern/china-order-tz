<?php

namespace App\Http\Requests\Admin;

use App\Models\Admin;
use App\Models\Product;
use App\Services\AdminProducts\VariantBulkActionService;
use App\Support\Admin\AdminPermissions;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ExecuteVariantBulkActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if (! $user instanceof Admin) {
            return false;
        }

        $actionKey = strtolower(trim((string) $this->input('action_key', '')));

        return match ($actionKey) {
            VariantBulkActionService::ACTION_SET_SELLING_PRICE,
            VariantBulkActionService::ACTION_SET_COST_PRICE => $user->hasAdminPermission(AdminPermissions::PRICING_MANAGE),
            VariantBulkActionService::ACTION_SET_COMMERCIAL_STOCK,
            VariantBulkActionService::ACTION_SET_INVENTORY_STOCK => $user->hasAdminPermission(AdminPermissions::INVENTORY_ADJUST),
            VariantBulkActionService::ACTION_ACTIVATE,
            VariantBulkActionService::ACTION_DEACTIVATE => $user->hasAdminPermission(AdminPermissions::CATALOG_UPDATE),
            default => false,
        };
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $product = $this->route('product');
        $productId = $product instanceof Product ? $product->id : $product;

        return [
            'action_key' => ['required', 'string', Rule::in(VariantBulkActionService::ACTIONS)],
            'variant_ids' => ['required', 'array', 'min:1', 'max:200'],
            'variant_ids.*' => [
                'required',
                'uuid',
                Rule::exists('product_variants', 'id')->where(
                    static fn ($query) => $query->where('product_id', $productId),
                ),
            ],
            'payload' => ['sometimes', 'array'],
            'payload.amount' => ['nullable', 'numeric', 'min:0'],
            'payload.cost_price' => ['nullable', 'numeric', 'min:0'],
            'payload.available_quantity' => ['nullable', 'integer', 'min:0'],
            'payload.on_hand' => ['nullable', 'integer', 'min:0'],
            'payload.quantity' => ['nullable', 'integer', 'min:0'],
            'payload.warehouse_code' => ['nullable', 'string', 'max:32'],
            'payload.reserved' => ['nullable', 'integer', 'min:0'],
            'payload.reorder_level' => ['nullable', 'integer', 'min:0'],
            'payload.safety_stock' => ['nullable', 'integer', 'min:0'],
            'payload.is_active' => ['nullable', 'boolean'],
        ];
    }
}
