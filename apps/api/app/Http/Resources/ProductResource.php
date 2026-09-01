<?php

namespace App\Http\Resources;

use App\Enums\ProductPricingModel;
use App\Models\Product;
use App\Services\AdminProducts\AdminProductListSummaryPresenter;
use App\Services\Catalog\CustomerProductMediaResolver;
use App\Services\Inventory\CatalogStockPresenter;
use App\Services\ProductConfiguration\LegacyConfigurationProductDetector;
use App\Support\Catalog\ProductConditionResolver;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Product */
class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $presenter = app(CatalogStockPresenter::class);
        $legacyConfigurationDetector = app(LegacyConfigurationProductDetector::class);
        $listSummary = app(AdminProductListSummaryPresenter::class);
        $mediaResolver = app(CustomerProductMediaResolver::class);
        $effectiveCondition = ProductConditionResolver::effectiveForProduct($this->resource);

        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'sku' => $this->sku,
            'description' => $this->description,
            'short_description' => $this->short_description,
            'price' => $this->price,
            'pricing_model' => $this->pricing_model?->value ?? ProductPricingModel::Simple->value,
            'minimum_order_quantity' => $this->minimum_order_quantity !== null
                ? (int) $this->minimum_order_quantity
                : null,
            'order_increment' => $this->order_increment !== null
                ? (int) $this->order_increment
                : null,
            'compare_at_price' => $this->compare_at_price,
            'cost_price' => $this->cost_price,
            'air_shipping_price' => $this->air_shipping_price,
            'sea_shipping_price' => $this->sea_shipping_price,
            'shipping_options' => ProductShippingOptionResource::collection($this->whenLoaded('shippingOptions')),
            'weight' => $this->weight,
            'dimensions' => $this->dimensions,
            'is_active' => $this->is_active,
            'is_featured' => $this->is_featured,
            'is_demo' => $this->is_demo,
            'status' => $this->lifecycle_status?->value,
            'lifecycle_status' => $this->lifecycle_status?->value,
            'visibility' => $this->visibility?->value ?? $this->visibility,
            'sort_order' => (int) ($this->sort_order ?? 0),
            'meta_title' => $this->meta_title,
            'meta_description' => $this->meta_description,
            'product_type_id' => $this->product_type_id,
            'catalog_product_type_id' => $this->catalog_product_type_id,
            'product_condition' => $effectiveCondition?->value,
            'product_condition_label' => ProductConditionResolver::label($effectiveCondition),
            'product_condition_eligible' => ProductConditionResolver::isEligible($this->catalogProductType),
            'legacy_configuration_product' => $legacyConfigurationDetector->isLegacyConfigurationProduct(
                $this->resource,
            ),
            'commerce_channel_id' => $this->commerce_channel_id,
            'store_id' => $this->store_id,
            'fulfillment_source' => $this->fulfillment_source,
            'commerce_channel' => new CommerceChannelResource($this->whenLoaded('commerceChannel')),
            // Additive ADMIN-08.1 list summaries (safe for existing consumers).
            'image' => $listSummary->image($this->resource),
            'store' => $listSummary->store($this->resource),
            'variants_count' => $listSummary->variantsCount($this->resource),
            'price_range' => $listSummary->priceRange($this->resource),
            'stock_summary' => $listSummary->stockSummary($this->resource),
            // Surface legacy integrity issues (active variants under a trashed parent).
            'catalog_integrity' => $this->when(
                $this->resource->trashed(),
                function () {
                    $orphans = array_key_exists('orphaned_active_variants_count', $this->resource->getAttributes())
                        ? (int) $this->resource->getAttribute('orphaned_active_variants_count')
                        : (int) $this->resource->variants()->count();

                    return [
                        'orphaned_active_variants_count' => $orphans,
                        'has_orphaned_active_variants' => $orphans > 0,
                    ];
                },
            ),
            'product_type' => new ProductTypeResource($this->whenLoaded('productType')),
            'catalog_product_type' => $this->whenLoaded('catalogProductType', fn () => [
                'id' => $this->catalogProductType?->id,
                'name' => $this->catalogProductType?->name,
                'slug' => $this->catalogProductType?->slug,
                'subcategory_id' => $this->catalogProductType?->subcategory_id,
            ]),
            'category' => new CategoryResource($this->whenLoaded('category')),
            'brand' => new BrandResource($this->whenLoaded('brand')),
            'supplier_id' => $this->supplier_id,
            'supplier' => new SupplierResource($this->whenLoaded('supplier')),
            'images' => $this->when(
                $this->relationLoaded('media') || $this->relationLoaded('images'),
                fn () => $mediaResolver->resolveAdminGallery($this->resource),
            ),
            'variants' => ProductVariantResource::collection($this->whenLoaded('variants')),
            'configurations' => ProductVariantResource::collection($this->whenLoaded('variants')),
            // Canonical simple Catalog Stock via StockResolver when inventory relation is in play.
            'inventory' => $this->when(
                $this->relationLoaded('inventory'),
                fn () => $presenter->simpleInventoryCollection($this->resource),
            ),
            'reviews' => ReviewResource::collection($this->whenLoaded('reviews')),
            'price_tiers' => ConfigurationPriceTierResource::collection($this->whenLoaded('priceTiers')),
            'average_rating' => $this->when(
                $this->relationLoaded('reviews'),
                fn () => round($this->reviews->avg('rating') ?? 0, 1)
            ),
            'deleted_at' => $this->deleted_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
