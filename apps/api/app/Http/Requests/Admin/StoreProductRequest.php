<?php

namespace App\Http\Requests\Admin;

use App\Enums\ProductLifecycleStatus;
use App\Enums\ProductVisibility;
use App\Enums\ShippingMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'unique:products,slug'],
            'commerce_channel_id' => ['sometimes', 'nullable', 'uuid', 'exists:commerce_channels,id'],
            'store_id' => ['sometimes', 'nullable', 'uuid', 'exists:stores,id'],
            'category_id' => ['required_without:catalog_product_type_id', 'nullable', 'uuid', 'exists:categories,id'],
            'catalog_product_type_id' => ['required_without:category_id', 'nullable', 'uuid', 'exists:catalog_product_types,id'],
            'brand_id' => ['nullable', 'uuid', 'exists:brands,id'],
            'supplier_id' => ['nullable', 'uuid', 'exists:suppliers,id'],
            'sku' => ['nullable', 'string', 'max:100', 'unique:products,sku'],
            'price' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'compare_at_price' => ['nullable', 'numeric', 'min:0'],
            'cost_price' => ['nullable', 'numeric', 'min:0'],
            'air_shipping_price' => ['nullable', 'numeric', 'min:0'],
            'sea_shipping_price' => ['nullable', 'numeric', 'min:0'],
            'shipping_options' => ['sometimes', 'array'],
            'shipping_options.*.transport_mode' => ['required_with:shipping_options', 'string', Rule::enum(ShippingMethod::class)],
            'shipping_options.*.price' => ['required_with:shipping_options', 'numeric', 'min:0'],
            'shipping_options.*.currency' => ['nullable', 'string', 'size:3'],
            'shipping_options.*.is_available' => ['sometimes', 'boolean'],
            'shipping_options.*.notes' => ['nullable', 'string', 'max:2000'],
            'shipping_options.*.sort_order' => ['sometimes', 'integer', 'min:0'],
            'weight' => ['nullable', 'numeric', 'min:0'],
            'stock_quantity' => ['nullable', 'integer', 'min:0'],
            'short_description' => ['nullable', 'string'],
            'description' => ['nullable', 'string'],
            'is_featured' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
            'is_demo' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:999999'],
            'visibility' => ['sometimes', 'string', Rule::in(array_column(ProductVisibility::cases(), 'value'))],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string'],
            'status' => ['sometimes'],
            'lifecycle_status' => ['sometimes', 'string', Rule::in(array_column(ProductLifecycleStatus::cases(), 'value'))],
            'price_tiers' => ['sometimes', 'array'],
            'price_tiers.*.min_quantity' => ['required_with:price_tiers', 'integer', 'min:1'],
            'price_tiers.*.tier_type' => ['sometimes', 'string', Rule::in(['fixed_unit', 'percent_off'])],
            'price_tiers.*.unit_price' => ['nullable', 'numeric', 'min:0'],
            'price_tiers.*.discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'configurations' => ['sometimes', 'array'],
            'configurations.*.id' => ['nullable', 'uuid'],
            'configurations.*.attribute_value_ids' => ['required_with:configurations', 'array', 'min:1'],
            'configurations.*.attribute_value_ids.*' => ['uuid', 'exists:product_attribute_values,id'],
            'configurations.*.sku' => ['nullable', 'string', 'max:100'],
            'configurations.*.stock_quantity' => ['required_with:configurations', 'integer', 'min:0'],
            'configurations.*.price' => ['nullable', 'numeric', 'min:0'],
            'configurations.*.barcode' => ['nullable', 'string', 'max:100'],
            'configurations.*.price_tiers' => ['sometimes', 'array'],
            'configurations.*.price_tiers.*.min_quantity' => ['required_with:configurations.*.price_tiers', 'integer', 'min:1'],
            'configurations.*.price_tiers.*.tier_type' => ['sometimes', 'string', Rule::in(['fixed_unit', 'percent_off'])],
            'configurations.*.price_tiers.*.unit_price' => ['nullable', 'numeric', 'min:0'],
            'configurations.*.price_tiers.*.discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('status') && ! is_bool($this->input('status'))) {
            $raw = $this->input('status');

            if (is_string($raw)) {
                $normalized = strtolower(trim($raw));
                if (in_array($normalized, ['draft', 'active', 'out_of_stock', 'archived'], true)) {
                    $input = $this->all();
                    $input['lifecycle_status'] = $normalized;
                    unset($input['status']);
                    $this->replace($input);
                } else {
                    $this->merge([
                        'status' => in_array($normalized, ['1', 'true', 'yes'], true),
                    ]);
                }
            } elseif (is_numeric($raw)) {
                $this->merge(['status' => (int) $raw === 1]);
            }
        }

        if ($this->has('status') && is_bool($this->input('status'))) {
            // Keep boolean legacy status for commerce forms.
        } elseif ($this->has('status') && $this->input('status') === null) {
            $input = $this->all();
            unset($input['status']);
            $this->replace($input);
        }

        foreach (['is_featured', 'is_demo', 'is_active'] as $field) {
            if ($this->has($field) && ! is_bool($this->input($field))) {
                $this->merge([
                    $field => filter_var($this->input($field), FILTER_VALIDATE_BOOLEAN),
                ]);
            }
        }

        if (! $this->has('stock_quantity') && $this->has('configurations')) {
            $this->merge(['stock_quantity' => 0]);
        }

        if ($this->exists('sku') && ! filled($this->input('sku'))) {
            $this->merge(['sku' => null]);
        }

        if (! $this->filled('lifecycle_status') && ! $this->has('status')) {
            $this->merge(['lifecycle_status' => ProductLifecycleStatus::Draft->value]);
        }
    }
}
