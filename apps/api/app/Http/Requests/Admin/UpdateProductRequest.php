<?php

namespace App\Http\Requests\Admin;

use App\Enums\ProductLifecycleStatus;
use App\Enums\ProductVisibility;
use App\Enums\ShippingMethod;
use App\Enums\CommerceChannelCode;
use App\Http\Requests\Concerns\RejectsTzLocalChinaFreight;
use App\Models\Admin;
use App\Models\CommerceChannel;
use App\Models\Product;
use App\Support\Admin\AdminPermissions;
use App\Support\ProductLifecycle;
use App\Support\Security\HtmlSanitizer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateProductRequest extends FormRequest
{
    use RejectsTzLocalChinaFreight;

    public function authorize(): bool
    {
        $user = $this->user();
        if (! $user instanceof Admin) {
            return false;
        }

        $lifecycle = $this->input('lifecycle_status');
        if (is_string($lifecycle)) {
            if ($lifecycle === ProductLifecycleStatus::Active->value
                || $lifecycle === ProductLifecycleStatus::OutOfStock->value) {
                if (! $user->hasAdminPermission(AdminPermissions::CATALOG_PUBLISH)
                    && ! $user->hasAdminPermission(AdminPermissions::CATALOG_UPDATE)) {
                    return false;
                }
            }

            if ($lifecycle === ProductLifecycleStatus::Archived->value) {
                return $user->hasAdminPermission(AdminPermissions::CATALOG_ARCHIVE)
                    || $user->hasAdminPermission(AdminPermissions::CATALOG_UPDATE);
            }

            if ($lifecycle === ProductLifecycleStatus::Draft->value
                && ! $user->hasAdminPermission(AdminPermissions::CATALOG_RESTORE)
                && ! $user->hasAdminPermission(AdminPermissions::CATALOG_UPDATE)) {
                return false;
            }
        }

        if ($this->exists('price') || $this->exists('price_tiers') || $this->exists('compare_at_price')) {
            if (! $user->hasAdminPermission(AdminPermissions::PRICING_MANAGE)
                && ! $user->hasAdminPermission(AdminPermissions::CATALOG_UPDATE)) {
                return false;
            }
        }

        if ($this->exists('stock_quantity')) {
            if (! $user->hasAdminPermission(AdminPermissions::INVENTORY_ADJUST)
                && ! $user->hasAdminPermission(AdminPermissions::CATALOG_UPDATE)) {
                return false;
            }
        }

        return $user->hasAdminPermission(AdminPermissions::CATALOG_UPDATE)
            || $user->hasAdminPermission(AdminPermissions::CATALOG_PUBLISH)
            || $user->hasAdminPermission(AdminPermissions::CATALOG_ARCHIVE);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var \App\Models\Product $product */
        $product = $this->route('product');

        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'slug' => ['sometimes', 'nullable', 'string', 'max:255', Rule::unique('products', 'slug')->ignore($product)],
            'commerce_channel_id' => ['sometimes', 'required', 'uuid', 'exists:commerce_channels,id'],
            'store_id' => ['sometimes', 'nullable', 'uuid', 'exists:stores,id'],
            'category_id' => ['sometimes', 'nullable', 'uuid', 'exists:categories,id'],
            'catalog_product_type_id' => ['sometimes', 'nullable', 'uuid', 'exists:catalog_product_types,id'],
            'brand_id' => ['nullable', 'uuid', 'exists:brands,id'],
            'supplier_id' => ['nullable', 'uuid', 'exists:suppliers,id'],
            'sku' => ['sometimes', 'nullable', 'string', 'max:100', Rule::unique('products', 'sku')->ignore($product)],
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
            'stock_quantity' => ['sometimes', 'nullable', 'integer', 'min:0'],
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

        foreach (['is_featured', 'is_demo', 'is_active'] as $field) {
            if ($this->has($field) && ! is_bool($this->input($field))) {
                $this->merge([
                    $field => filter_var($this->input($field), FILTER_VALIDATE_BOOLEAN),
                ]);
            }
        }

        if ($this->exists('sku') && ! filled($this->input('sku'))) {
            $this->merge(['sku' => null]);
        }

        $htmlFields = [];
        foreach (['description', 'short_description'] as $field) {
            if ($this->exists($field) && is_string($this->input($field))) {
                $htmlFields[$field] = HtmlSanitizer::sanitize($this->input($field));
            }
        }
        if ($htmlFields !== []) {
            $this->merge($htmlFields);
        }
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            /** @var Product $product */
            $product = $this->route('product');

            if ($this->has('commerce_channel_id') && filled($product->commerce_channel_id)) {
                $existingChannelId = (string) $product->commerce_channel_id;
                $incomingChannelId = (string) $this->input('commerce_channel_id');

                if ($existingChannelId !== $incomingChannelId) {
                    $validator->errors()->add(
                        'commerce_channel_id',
                        'Commerce channel cannot be changed after product creation.',
                    );
                }
            }

            $channelId = $this->input('commerce_channel_id', $product->commerce_channel_id);
            $channel = is_string($channelId) && $channelId !== ''
                ? CommerceChannel::query()->find($channelId)
                : null;
            $channelCode = CommerceChannelCode::tryFrom((string) ($channel?->code ?? ''))
                ?? CommerceChannelCode::fromFulfillmentSource($product->fulfillment_source ?? null);

            $this->rejectTzLocalChinaFreight($validator, $channelCode);

            if ($channelCode !== CommerceChannelCode::TzLocal) {
                return;
            }

            if (! $this->requestTargetsActiveLifecycle($product)) {
                return;
            }

            $storeId = $this->has('store_id') ? $this->input('store_id') : $product->store_id;
            if (blank($storeId)) {
                $validator->errors()->add(
                    'store_id',
                    'TZ_LOCAL products must belong to a store.',
                );
            }
        });
    }

    private function requestTargetsActiveLifecycle(Product $product): bool
    {
        $isActive = $this->has('is_active')
            ? filter_var($this->input('is_active'), FILTER_VALIDATE_BOOLEAN)
            : (bool) $product->is_active;

        if (! $isActive) {
            return false;
        }

        if ($this->has('lifecycle_status') || $this->has('status')) {
            $lifecycle = ProductLifecycle::resolveFromRequest(
                $this->input('lifecycle_status'),
                $this->has('status') ? filter_var($this->input('status'), FILTER_VALIDATE_BOOLEAN) : null,
            );

            return $lifecycle->isPurchasable();
        }

        $lifecycle = $product->lifecycle_status;
        if ($lifecycle instanceof ProductLifecycleStatus) {
            return $lifecycle->isPurchasable();
        }

        return ProductLifecycleStatus::tryFromMixed($lifecycle)?->isPurchasable() ?? false;
    }
}
