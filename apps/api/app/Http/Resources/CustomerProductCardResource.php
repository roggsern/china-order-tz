<?php

namespace App\Http\Resources;

use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Product */
class CustomerProductCardResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $this->name,
            'short_description' => $this->short_description,
            'price' => $this->price,
            'compare_at_price' => $this->compare_at_price,
            'is_featured' => $this->is_featured,
            'primary_image' => $this->resolvePrimaryImage(),
            'category' => new CustomerCategoryResource($this->whenLoaded('category')),
            'brand' => new CustomerBrandResource($this->whenLoaded('brand')),
            'average_rating' => $this->formatAverageRating(),
            'review_count' => (int) ($this->review_count ?? 0),
            'shipping_prices' => [
                'air' => $this->shippingPriceForMethod('air'),
                'sea' => $this->shippingPriceForMethod('sea'),
            ],
            'requires_china_shipping' => $this->requiresChinaShipping(),
            'commerce_channel_code' => $this->when(
                $this->relationLoaded('commerceChannel') && $this->commerceChannel !== null,
                fn () => $this->commerceChannel->code,
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
