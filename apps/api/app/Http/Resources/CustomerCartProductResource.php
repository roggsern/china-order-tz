<?php

namespace App\Http\Resources;

use App\Models\ProductImage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Explicit customer/public allowlist for products nested under cart/checkout.
 * Never serializes supplier, costs, or procurement fields — even if loaded.
 *
 * @mixin \App\Models\Product
 */
class CustomerCartProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $this->name,
            'short_description' => $this->short_description,
            'sku' => $this->sku,
            'price' => $this->price,
            'compare_at_price' => $this->compare_at_price,
            'primary_image' => $this->resolvePrimaryImage(),
            'images' => CustomerProductImageResource::collection($this->whenLoaded('images')),
            'brand' => new CustomerBrandResource($this->whenLoaded('brand')),
            'category' => new CustomerCategoryResource($this->whenLoaded('category')),
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
}
