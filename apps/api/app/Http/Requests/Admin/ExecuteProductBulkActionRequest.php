<?php

namespace App\Http\Requests\Admin;

use App\Models\Admin;
use App\Services\AdminProducts\ProductBulkActionService;
use App\Support\Admin\AdminPermissions;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ExecuteProductBulkActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if (! $user instanceof Admin) {
            return false;
        }

        $actionKey = strtolower(trim((string) $this->input('action_key', '')));

        return match ($actionKey) {
            ProductBulkActionService::ACTION_PUBLISH => $user->hasAdminPermission(AdminPermissions::CATALOG_PUBLISH)
                || $user->hasAdminPermission(AdminPermissions::CATALOG_UPDATE),
            ProductBulkActionService::ACTION_ARCHIVE => $user->hasAdminPermission(AdminPermissions::CATALOG_ARCHIVE)
                || $user->hasAdminPermission(AdminPermissions::CATALOG_UPDATE),
            ProductBulkActionService::ACTION_PRICING_PERCENTAGE_INCREASE,
            ProductBulkActionService::ACTION_PRICING_PERCENTAGE_DECREASE,
            ProductBulkActionService::ACTION_PRICING_FIXED => $user->hasAdminPermission(AdminPermissions::PRICING_MANAGE),
            ProductBulkActionService::ACTION_INVENTORY_INCREASE,
            ProductBulkActionService::ACTION_INVENTORY_DECREASE,
            ProductBulkActionService::ACTION_INVENTORY_SET => $user->hasAdminPermission(AdminPermissions::INVENTORY_ADJUST),
            default => false,
        };
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'action_key' => ['required', 'string', Rule::in(ProductBulkActionService::ACTIONS)],
            'product_ids' => ['required', 'array', 'min:1', 'max:200'],
            'product_ids.*' => ['required', 'uuid'],
            'payload' => ['sometimes', 'array'],
            'payload.percent' => ['nullable', 'numeric', 'gt:0', 'max:1000'],
            'payload.amount' => ['nullable', 'numeric', 'min:0'],
            'payload.quantity' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
