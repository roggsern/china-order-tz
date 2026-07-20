<?php

namespace App\Http\Resources;

use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Product */
class CustomerProductDetailResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $this->name,
            'description' => $this->description,
            'short_description' => $this->short_description,
            'price' => $this->price,
            'compare_at_price' => $this->compare_at_price,
            'weight' => $this->weight,
            'dimensions' => $this->dimensions,
            'category' => new CustomerCategoryResource($this->whenLoaded('category')),
            'brand' => new CustomerBrandResource($this->whenLoaded('brand')),
            'primary_image' => $this->resolvePrimaryImage(),
            'images' => CustomerProductImageResource::collection($this->whenLoaded('images')),
            'variants' => CustomerProductVariantResource::collection($this->whenLoaded('variants')),
            'configurations' => CustomerProductVariantResource::collection($this->whenLoaded('variants')),
            'product_type_id' => $this->product_type_id,
            'average_rating' => $this->formatAverageRating(),
            'review_count' => (int) ($this->review_count ?? 0),
            'shipping_prices' => [
                'air' => $this->shippingPriceForMethod('air'),
                'sea' => $this->shippingPriceForMethod('sea'),
            ],
            'shipping_options' => $this->when(
                $this->relationLoaded('shippingOptions'),
                fn () => ProductShippingOptionResource::collection(
                    $this->shippingOptions->where('is_available', true)->values()
                )
            ),
            'requires_china_shipping' => $this->requiresChinaShipping(),
            'commerce_channel' => $this->when(
                $this->relationLoaded('commerceChannel') && $this->commerceChannel !== null,
                fn () => [
                    'id' => $this->commerceChannel->id,
                    'code' => $this->commerceChannel->code,
                    'name' => $this->commerceChannel->name,
                    'customer_label' => \App\Enums\CommerceChannelCode::tryFrom($this->commerceChannel->code)
                        ?->customerSourceLabel(),
                ],
            ),
            'commerce_source_label' => $this->resolveCommerceSourceLabel(),
        ];
    }

    private function resolveCommerceSourceLabel(): string
    {
        if ($this->relationLoaded('commerceChannel') && $this->commerceChannel !== null) {
            return \App\Enums\CommerceChannelCode::tryFrom($this->commerceChannel->code)
                ?->customerSourceLabel()
                ?? 'Imported From China';
        }

        $code = \App\Enums\CommerceChannelCode::fromFulfillmentSource($this->fulfillment_source ?? null);

        return $code->customerSourceLabel();
    }

    private function resolvePrimaryImage(): ?array
    {
        if ($this->relationLoaded('images')) {
            $image = $this->images->firstWhere('is_primary', true)
                ?? $this->images->sortBy('sort_order')->first();

            return $image instanceof ProductImage
                ? (new CustomerProductImageResource($image))->resolve()
                : null;
        }

        $image = $this->primaryImage();

        return $image instanceof ProductImage
            ? (new CustomerProductImageResource($image))->resolve()
            : null;
    }

    private function formatAverageRating(): float
    {
        if ($this->average_rating === null) {
            return 0.0;
        }

        return round((float) $this->average_rating, 1);
    }
}
