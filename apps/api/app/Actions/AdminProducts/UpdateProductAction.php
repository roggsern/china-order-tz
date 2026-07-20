<?php

namespace App\Actions\AdminProducts;

use App\Enums\CommerceChannelCode;
use App\Enums\ProductVisibility;
use App\Events\Audit\ProductUpdated;
use App\Events\Commerce\CommerceChannelAssigned;
use App\Http\Requests\Admin\UpdateProductRequest;
use App\Models\Admin;
use App\Models\CatalogProductType;
use App\Models\Category;
use App\Models\CommerceChannel;
use App\Models\Inventory;
use App\Models\Product;
use App\Services\Audit\ActivityLogFormatter;
use App\Services\Catalog\GenerateProductSku;
use App\Services\Pricing\SyncConfigurationPriceTiers;
use App\Services\ProductConfiguration\ResolveTypeFromCategory;
use App\Services\ProductConfiguration\SyncProductConfigurations;
use App\Services\ProductShipping\ProductShippingOptionEngine;
use App\Support\ProductLifecycle;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class UpdateProductAction
{
    public function __construct(
        private readonly ResolveTypeFromCategory $resolveTypeFromCategory,
        private readonly SyncProductConfigurations $syncProductConfigurations,
        private readonly SyncConfigurationPriceTiers $syncConfigurationPriceTiers,
        private readonly GenerateProductSku $generateProductSku,
        private readonly ProductShippingOptionEngine $shippingOptionEngine,
        private readonly ActivityLogFormatter $activityLogFormatter,
    ) {}

    public function handle(UpdateProductRequest $request, Product $product): Product
    {
        $validated = $request->validated();

        return DB::transaction(function () use ($validated, $product) {
            $before = $product->only([
                'name',
                'sku',
                'price',
                'compare_at_price',
                'air_shipping_price',
                'sea_shipping_price',
                'lifecycle_status',
                'is_active',
                'category_id',
                'commerce_channel_id',
            ]);

            $productData = [];
            $assignedChannel = null;

            $assignable = [
                'name',
                'brand_id',
                'supplier_id',
                'sku',
                'price',
                'compare_at_price',
                'cost_price',
                'air_shipping_price',
                'sea_shipping_price',
                'weight',
                'short_description',
                'description',
                'meta_title',
                'meta_description',
                'is_demo',
                'sort_order',
            ];

            foreach ($assignable as $field) {
                if (array_key_exists($field, $validated)) {
                    $productData[$field] = $validated[$field];
                }
            }

            if (array_key_exists('slug', $validated) && filled($validated['slug'])) {
                $productData['slug'] = $this->generateUniqueSlug((string) $validated['slug'], $product->id, treatAsSlug: true);
            } elseif (array_key_exists('name', $validated) && $validated['name'] !== $product->name) {
                $productData['slug'] = $this->generateUniqueSlug($validated['name'], $product->id);
            }

            if (array_key_exists('lifecycle_status', $validated) || array_key_exists('status', $validated)) {
                $lifecycle = ProductLifecycle::resolveFromRequest(
                    $validated['lifecycle_status'] ?? null,
                    array_key_exists('status', $validated) ? (bool) $validated['status'] : null,
                );
                $productData['lifecycle_status'] = $lifecycle;

                if (! array_key_exists('is_active', $validated)) {
                    $productData['is_active'] = $lifecycle->syncIsActiveFlag();
                }
            }

            if (array_key_exists('is_active', $validated)) {
                $productData['is_active'] = (bool) $validated['is_active'];
            }

            if (array_key_exists('is_featured', $validated)) {
                $productData['is_featured'] = (bool) $validated['is_featured'];
            }

            if (array_key_exists('visibility', $validated)) {
                $productData['visibility'] = ProductVisibility::tryFromMixed($validated['visibility'])
                    ?? ProductVisibility::Public;
            }

            if (array_key_exists('commerce_channel_id', $validated)) {
                $assignedChannel = CommerceChannel::query()
                    ->where('id', $validated['commerce_channel_id'])
                    ->where('is_active', true)
                    ->firstOrFail();
                $channelCode = CommerceChannelCode::tryFrom($assignedChannel->code)
                    ?? CommerceChannelCode::ChinaImport;
                $productData['commerce_channel_id'] = $assignedChannel->id;
                $productData['fulfillment_source'] = $channelCode->fulfillmentSource();
            }

            [$category, $catalogProductTypeId] = $this->resolveCategoryAndCatalogType(
                $validated,
                $product,
            );

            $productData['category_id'] = $category->id;
            if (array_key_exists('catalog_product_type_id', $validated) || $catalogProductTypeId !== null) {
                $productData['catalog_product_type_id'] = $catalogProductTypeId;
            }

            $productType = $this->resolveTypeFromCategory->handle($category);
            $productData['product_type_id'] = $productType?->id;

            if (array_key_exists('sku', $validated) && ! filled($validated['sku'])) {
                unset($productData['sku']);
                if ($product->sku === null || $product->sku === '') {
                    $productData['sku'] = $this->generateProductSku->handle($category);
                }
            }

            $configurations = $validated['configurations'] ?? null;

            if (is_array($configurations) && $configurations !== [] && $productType === null) {
                throw ValidationException::withMessages([
                    'category_id' => ['Selected category has no Product Type. Assign a type before creating configurations.'],
                ]);
            }

            if ($productData !== []) {
                $product->update($productData);
            }

            if (array_key_exists('stock_quantity', $validated) && ! is_array($configurations)) {
                Inventory::query()->updateOrCreate(
                    [
                        'product_id' => $product->id,
                        'product_variant_id' => null,
                    ],
                    [
                        'quantity' => $validated['stock_quantity'] ?? 0,
                    ],
                );
            }

            if (is_array($configurations) && $productType !== null) {
                Inventory::query()->updateOrCreate(
                    [
                        'product_id' => $product->id,
                        'product_variant_id' => null,
                    ],
                    [
                        'quantity' => 0,
                    ],
                );

                $this->syncProductConfigurations->handle($product->fresh(), $productType, $configurations);
            }

            if (array_key_exists('price_tiers', $validated)) {
                $this->syncConfigurationPriceTiers->handle(
                    $product->fresh(),
                    null,
                    is_array($validated['price_tiers']) ? $validated['price_tiers'] : [],
                );
            }

            if (array_key_exists('shipping_options', $validated)) {
                $this->shippingOptionEngine->syncForProduct(
                    $product->fresh() ?? $product,
                    is_array($validated['shipping_options']) ? $validated['shipping_options'] : [],
                );
            } elseif (
                array_key_exists('air_shipping_price', $validated)
                || array_key_exists('sea_shipping_price', $validated)
            ) {
                $rows = [];
                $air = $validated['air_shipping_price'] ?? $product->air_shipping_price;
                $sea = $validated['sea_shipping_price'] ?? $product->sea_shipping_price;
                if ($air !== null) {
                    $rows[] = [
                        'transport_mode' => 'air',
                        'price' => $air,
                        'currency' => 'TZS',
                        'is_available' => true,
                        'sort_order' => 0,
                    ];
                }
                if ($sea !== null) {
                    $rows[] = [
                        'transport_mode' => 'sea',
                        'price' => $sea,
                        'currency' => 'TZS',
                        'is_available' => true,
                        'sort_order' => 1,
                    ];
                }
                $this->shippingOptionEngine->syncForProduct($product->fresh() ?? $product, $rows);
            }

            $fresh = $product->fresh([
                'commerceChannel',
                'category.department',
                'brand',
                'catalogProductType.subcategory',
                'productType',
                'inventory',
                'images',
                'priceTiers',
                'shippingOptions',
                'variants.attributeValues.attribute',
                'variants.inventory',
                'variants.priceTiers',
            ]);

            $after = ($fresh ?? $product)->only(array_keys($before));
            $diff = $this->activityLogFormatter->diffAttributes($before, $after);
            $admin = auth('sanctum')->user();
            if ($diff['old'] !== [] || $diff['new'] !== []) {
                event(ProductUpdated::fromChanges(
                    $fresh ?? $product,
                    $diff['old'],
                    $diff['new'],
                    $admin instanceof Admin ? $admin : null,
                ));
            }

            if (
                $assignedChannel !== null
                && ($before['commerce_channel_id'] ?? null) !== $assignedChannel->id
            ) {
                event(new CommerceChannelAssigned(
                    $fresh ?? $product,
                    $assignedChannel,
                    $admin instanceof Admin ? Admin::class : null,
                    $admin instanceof Admin ? $admin->id : null,
                ));
            }

            return $fresh;
        });
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array{0: Category, 1: ?string}
     */
    private function resolveCategoryAndCatalogType(array $validated, Product $product): array
    {
        $catalogProductTypeId = $validated['catalog_product_type_id']
            ?? $product->catalog_product_type_id;
        $categoryId = $validated['category_id'] ?? $product->category_id;

        if (array_key_exists('catalog_product_type_id', $validated) && filled($validated['catalog_product_type_id'])) {
            $catalogType = CatalogProductType::query()->findOrFail($validated['catalog_product_type_id']);
            $derivedCategoryId = $catalogType->subcategory_id;

            if (
                array_key_exists('category_id', $validated)
                && filled($validated['category_id'])
                && $validated['category_id'] !== $derivedCategoryId
            ) {
                throw ValidationException::withMessages([
                    'category_id' => ['Category must match the selected catalog product type parent.'],
                ]);
            }

            return [
                Category::query()->findOrFail($derivedCategoryId),
                $catalogType->id,
            ];
        }

        if (array_key_exists('catalog_product_type_id', $validated) && ! filled($validated['catalog_product_type_id'])) {
            $catalogProductTypeId = null;
        }

        return [
            Category::query()->findOrFail($categoryId),
            $catalogProductTypeId,
        ];
    }

    private function generateUniqueSlug(string $value, string $ignoreProductId, bool $treatAsSlug = false): string
    {
        $slug = Str::slug($value);
        $original = $slug !== '' ? $slug : 'product';
        $slug = $original;
        $counter = 1;

        while (
            Product::query()
                ->where('slug', $slug)
                ->where('id', '!=', $ignoreProductId)
                ->exists()
        ) {
            $slug = $original.'-'.$counter;
            $counter++;
        }

        return $slug;
    }
}
