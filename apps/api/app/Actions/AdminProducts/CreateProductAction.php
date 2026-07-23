<?php

namespace App\Actions\AdminProducts;

use App\Enums\CommerceChannelCode;
use App\Enums\ProductVisibility;
use App\Events\Audit\ProductCreated;
use App\Events\Commerce\CommerceChannelAssigned;
use App\Http\Requests\Admin\StoreProductRequest;
use App\Models\Admin;
use App\Models\CatalogProductType;
use App\Models\Category;
use App\Models\CommerceChannel;
use App\Models\Product;
use App\Services\Catalog\GenerateProductSku;
use App\Services\Commerce\CommerceChannelResolver;
use App\Services\Inventory\AdminInventoryApplicationService;
use App\Services\Pricing\SyncConfigurationPriceTiers;
use App\Services\ProductConfiguration\ResolveTypeFromCategory;
use App\Services\ProductConfiguration\SyncProductConfigurations;
use App\Enums\ProductLifecycleStatus;
use App\Services\ProductPurchasability\ProductPurchasabilityPolicy;
use App\Services\ProductShipping\ProductShippingOptionEngine;
use App\Support\ProductLifecycle;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CreateProductAction
{
    public function __construct(
        private readonly ResolveTypeFromCategory $resolveTypeFromCategory,
        private readonly SyncProductConfigurations $syncProductConfigurations,
        private readonly SyncConfigurationPriceTiers $syncConfigurationPriceTiers,
        private readonly GenerateProductSku $generateProductSku,
        private readonly ProductShippingOptionEngine $shippingOptionEngine,
        private readonly CommerceChannelResolver $commerceChannelResolver,
        private readonly ProductPurchasabilityPolicy $purchasabilityPolicy,
        private readonly AdminInventoryApplicationService $adminInventory,
    ) {}

    public function handle(StoreProductRequest $request): Product
    {
        $validated = $request->validated();

        return DB::transaction(function () use ($validated) {
            $name = $validated['name'];
            $slug = filled($validated['slug'] ?? null)
                ? $this->generateUniqueSlug((string) $validated['slug'], treatAsSlug: true)
                : $this->generateUniqueSlug($name);

            [$category, $catalogProductTypeId] = $this->resolveCategoryAndCatalogType($validated);
            $productType = $this->resolveTypeFromCategory->handle($category);

            $configurations = $validated['configurations'] ?? null;

            if (is_array($configurations) && $configurations !== [] && $productType === null) {
                throw ValidationException::withMessages([
                    'category_id' => ['Selected category has no Product Type. Assign a type before creating configurations.'],
                ]);
            }

            $productStock = (int) ($validated['stock_quantity'] ?? 0);
            if (is_array($configurations) && $configurations !== []) {
                $productStock = 0;
            }

            $lifecycle = ProductLifecycle::resolveFromRequest(
                $validated['lifecycle_status'] ?? null,
                array_key_exists('status', $validated) ? (bool) $validated['status'] : null,
            );

            $isActive = array_key_exists('is_active', $validated)
                ? (bool) $validated['is_active']
                : $lifecycle->syncIsActiveFlag();

            $sku = filled($validated['sku'] ?? null)
                ? (string) $validated['sku']
                : $this->generateProductSku->handle($category);

            $visibility = ProductVisibility::tryFromMixed($validated['visibility'] ?? null)
                ?? ProductVisibility::Public;

            $channel = filled($validated['commerce_channel_id'] ?? null)
                ? CommerceChannel::query()
                    ->where('id', $validated['commerce_channel_id'])
                    ->where('is_active', true)
                    ->firstOrFail()
                : $this->commerceChannelResolver->channelByCode(CommerceChannelCode::ChinaImport);

            $channelCode = CommerceChannelCode::tryFrom($channel->code) ?? CommerceChannelCode::ChinaImport;

            $storeId = $validated['store_id'] ?? null;
            if ($channelCode === CommerceChannelCode::TzLocal && blank($storeId)) {
                throw ValidationException::withMessages([
                    'store_id' => ['TZ_LOCAL products must belong to a store.'],
                ]);
            }
            if ($channelCode === CommerceChannelCode::ChinaImport) {
                $storeId = null;
            }

            $product = Product::create([
                'name' => $name,
                'slug' => $slug,
                'commerce_channel_id' => $channel->id,
                'store_id' => $storeId,
                'fulfillment_source' => $channelCode->fulfillmentSource(),
                'category_id' => $category->id,
                'catalog_product_type_id' => $catalogProductTypeId,
                'brand_id' => $validated['brand_id'] ?? null,
                'supplier_id' => $validated['supplier_id'] ?? null,
                'product_type_id' => $productType?->id,
                'sku' => $sku,
                'price' => $validated['price'] ?? 0,
                'compare_at_price' => $validated['compare_at_price'] ?? null,
                'cost_price' => $validated['cost_price'] ?? null,
                'air_shipping_price' => $validated['air_shipping_price'] ?? null,
                'sea_shipping_price' => $validated['sea_shipping_price'] ?? null,
                'weight' => $validated['weight'] ?? null,
                'short_description' => $validated['short_description'] ?? null,
                'description' => $validated['description'] ?? null,
                'is_active' => $isActive,
                'lifecycle_status' => $lifecycle,
                'visibility' => $visibility,
                'sort_order' => (int) ($validated['sort_order'] ?? 0),
                'is_demo' => (bool) ($validated['is_demo'] ?? false),
                'is_featured' => (bool) ($validated['is_featured'] ?? false),
                'meta_title' => $validated['meta_title'] ?? null,
                'meta_description' => $validated['meta_description'] ?? null,
            ]);

            /** @var Admin|null $admin */
            $admin = Auth::user() instanceof Admin ? Auth::user() : null;
            $this->adminInventory->setSimpleProductStock(
                product: $product,
                targetQuantity: $productStock,
                actor: $admin,
                reason: 'Admin product create — opening stock',
            );

            if (is_array($configurations) && $configurations !== [] && $productType !== null) {
                $this->syncProductConfigurations->handle($product, $productType, $configurations);
            }

            if (array_key_exists('price_tiers', $validated)) {
                $this->syncConfigurationPriceTiers->handle(
                    $product,
                    null,
                    is_array($validated['price_tiers']) ? $validated['price_tiers'] : [],
                );
            }

            if (array_key_exists('shipping_options', $validated)) {
                $this->shippingOptionEngine->syncForProduct(
                    $product,
                    is_array($validated['shipping_options']) ? $validated['shipping_options'] : [],
                );
            } elseif (
                array_key_exists('air_shipping_price', $validated)
                || array_key_exists('sea_shipping_price', $validated)
            ) {
                $this->syncShippingOptionsFromLegacyPrices($product, $validated);
            }

            $product = $product->fresh([
                'catalogProductType',
                'category',
                'inventory',
                'variants.prices',
                'variants.inventories',
            ]) ?? $product;

            if ($lifecycle === ProductLifecycleStatus::Active) {
                $this->purchasabilityPolicy->assertPublishable($product);
            }

            return tap($product->fresh([
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
                'variants.inventories',
                'variants.priceTiers',
            ]), function (Product $created) use ($channel): void {
                $admin = auth('sanctum')->user();
                event(ProductCreated::fromProduct(
                    $created,
                    $admin instanceof Admin ? $admin : null,
                ));
                event(new CommerceChannelAssigned(
                    $created,
                    $channel,
                    $admin instanceof Admin ? Admin::class : null,
                    $admin instanceof Admin ? $admin->id : null,
                ));
            });
        });
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function syncShippingOptionsFromLegacyPrices(Product $product, array $validated): void
    {
        $rows = [];
        if (($validated['air_shipping_price'] ?? null) !== null) {
            $rows[] = [
                'transport_mode' => 'air',
                'price' => $validated['air_shipping_price'],
                'currency' => 'TZS',
                'is_available' => true,
                'sort_order' => 0,
            ];
        }
        if (($validated['sea_shipping_price'] ?? null) !== null) {
            $rows[] = [
                'transport_mode' => 'sea',
                'price' => $validated['sea_shipping_price'],
                'currency' => 'TZS',
                'is_available' => true,
                'sort_order' => 1,
            ];
        }

        $this->shippingOptionEngine->syncForProduct($product, $rows);
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array{0: Category, 1: ?string}
     */
    private function resolveCategoryAndCatalogType(array $validated): array
    {
        $catalogProductTypeId = $validated['catalog_product_type_id'] ?? null;
        $categoryId = $validated['category_id'] ?? null;

        if (is_string($catalogProductTypeId) && $catalogProductTypeId !== '') {
            $catalogType = CatalogProductType::query()->findOrFail($catalogProductTypeId);
            $derivedCategoryId = $catalogType->subcategory_id;

            if (is_string($categoryId) && $categoryId !== '' && $categoryId !== $derivedCategoryId) {
                throw ValidationException::withMessages([
                    'category_id' => ['Category must match the selected catalog product type parent.'],
                ]);
            }

            return [
                Category::query()->findOrFail($derivedCategoryId),
                $catalogType->id,
            ];
        }

        return [
            Category::query()->findOrFail($categoryId),
            null,
        ];
    }

    private function generateUniqueSlug(string $value, bool $treatAsSlug = false): string
    {
        $slug = $treatAsSlug ? Str::slug($value) : Str::slug($value);
        $original = $slug !== '' ? $slug : 'product';
        $slug = $original;
        $counter = 1;

        while (Product::where('slug', $slug)->exists()) {
            $slug = $original.'-'.$counter;
            $counter++;
        }

        return $slug;
    }
}
