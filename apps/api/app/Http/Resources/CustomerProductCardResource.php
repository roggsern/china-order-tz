<?php

namespace App\Http\Resources;

use App\Http\Resources\Concerns\PresentsCustomerCatalogAvailability;
use App\Http\Resources\Concerns\PresentsCustomerCatalogStock;
use App\Http\Resources\Concerns\PresentsCustomerCatalogPrice;
use App\Models\Product;
use App\Services\Catalog\CustomerProductMediaResolver;
use App\Services\Inventory\CatalogStockPresenter;
use App\Support\Catalog\ProductConditionResolver;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Product */
class CustomerProductCardResource extends JsonResource
{
    use PresentsCustomerCatalogAvailability;
    use PresentsCustomerCatalogPrice;
    use PresentsCustomerCatalogStock;

    /**
     * Canonical lightweight eager-load graph for every customer-facing product card.
     * Includes TZ_LOCAL store_id on nested variants.product for warehouse remap.
     *
     * @return array<int|string, mixed>
     */
    public static function listingEagerLoads(): array
    {
        return array_merge([
            'commerceChannel:id,name,code',
            'category:id,name,slug',
            'brand:id,name,slug',
            'store:id,name,slug',
            'catalogProductType:id,name',
        ], CustomerProductMediaResolver::catalogListingEagerLoads(), CatalogStockPresenter::catalogListingEagerLoads());
    }

    public function toArray(Request $request): array
    {
        $effectiveCondition = ProductConditionResolver::effectiveForProduct($this->resource);

        return [
            ...$this->customerCatalogAvailability(),
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $this->name,
            'short_description' => $this->short_description,
            'price' => $this->resolvedCatalogDisplayPrice(),
            'compare_at_price' => $this->compare_at_price,
            'is_featured' => $this->is_featured,
            'product_condition' => $effectiveCondition?->value,
            'product_condition_label' => $effectiveCondition?->storefrontBadge(),
            'primary_image' => app(CustomerProductMediaResolver::class)->resolvePrimary($this->resource),
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
            'stock' => $this->when(
                $this->usesSimpleProductStockPath(),
                fn () => $this->simpleProductAvailableStock(),
            ),
            'in_stock' => $this->when(
                $this->usesSimpleProductStockPath(),
                fn () => $this->simpleProductAvailableStock() > 0,
            ),
            'inventory' => $this->when(
                $this->usesSimpleProductStockPath() && $this->simpleProductInventoryContract() !== null,
                fn () => $this->simpleProductInventoryContract(),
            ),
            // Listing cards need stock signals only — full variant media/attributes belong on PDP.
            'variants' => CustomerProductListingVariantResource::collection($this->whenLoaded('variants')),
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

    private function formatAverageRating(): float
    {
        if ($this->average_rating === null) {
            return 0.0;
        }

        return round((float) $this->average_rating, 1);
    }
}
