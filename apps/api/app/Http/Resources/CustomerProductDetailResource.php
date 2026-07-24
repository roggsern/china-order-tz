<?php

namespace App\Http\Resources;

use App\Enums\PurchasabilityPath;
use App\Models\Product;
use App\Services\Catalog\CustomerProductMediaResolver;
use App\Services\Inventory\CatalogStockPresenter;
use App\Services\ProductPurchasability\ProductPurchasabilityPolicy;
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
            'primary_image' => app(CustomerProductMediaResolver::class)->resolvePrimary($this->resource),
            'images' => app(CustomerProductMediaResolver::class)->resolveGallery($this->resource),
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
        ];
    }

    private function usesSimpleProductStockPath(): bool
    {
        return app(ProductPurchasabilityPolicy::class)->resolvePath($this->resource) === PurchasabilityPath::Simple;
    }

    private function simpleProductAvailableStock(): int
    {
        return app(CatalogStockPresenter::class)->availableForSimple($this->resource);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function simpleProductInventoryContract(): ?array
    {
        $presenter = app(CatalogStockPresenter::class);
        $stock = $presenter->resolveForProduct($this->resource);

        if (! $stock->resolved) {
            return null;
        }

        return $presenter->toInventoryContract($stock, includeWarehouseLocation: false);
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
