<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Product */
class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'sku' => $this->sku,
            'description' => $this->description,
            'short_description' => $this->short_description,
            'price' => $this->price,
            'compare_at_price' => $this->compare_at_price,
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
            'commerce_channel_id' => $this->commerce_channel_id,
            'fulfillment_source' => $this->fulfillment_source,
            'commerce_channel' => new CommerceChannelResource($this->whenLoaded('commerceChannel')),
            'product_type' => new ProductTypeResource($this->whenLoaded('productType')),
            'catalog_product_type' => $this->whenLoaded('catalogProductType', fn () => [
                'id' => $this->catalogProductType?->id,
                'name' => $this->catalogProductType?->name,
                'slug' => $this->catalogProductType?->slug,
                'subcategory_id' => $this->catalogProductType?->subcategory_id,
            ]),
            'category' => new CategoryResource($this->whenLoaded('category')),
            'brand' => new BrandResource($this->whenLoaded('brand')),
            'supplier' => new SupplierResource($this->whenLoaded('supplier')),
            'images' => ProductImageResource::collection($this->whenLoaded('images')),
            'variants' => ProductVariantResource::collection($this->whenLoaded('variants')),
            'configurations' => ProductVariantResource::collection($this->whenLoaded('variants')),
            'inventory' => InventoryResource::collection($this->whenLoaded('inventory')),
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
