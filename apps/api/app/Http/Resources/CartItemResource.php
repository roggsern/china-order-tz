<?php

namespace App\Http\Resources;

use App\Models\CartItem;
use App\Services\Cart\CartProductPricingQuantity;
use App\Services\Catalog\CustomerProductMediaResolver;
use App\Services\Inventory\StockResolver;
use App\Services\Pricing\CommercePricingResolver;
use App\Services\Pricing\DTOs\CommercePricingContext;
use App\Services\Pricing\PresentVolumePricing;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin CartItem */
class CartItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $available = null;
        $resolver = app(StockResolver::class);

        if (filled($this->product_variant_id) && $this->relationLoaded('variant') && $this->variant !== null) {
            $product = $this->relationLoaded('product') ? $this->product : null;
            $stock = $resolver->resolveVariantProduct($this->variant, null, $product);
            $available = $stock->resolved ? $stock->quantityAvailable : null;
        }

        $productPayload = $this->relationLoaded('product') && $this->product !== null
            ? (new CustomerCartProductResource($this->product))->resolve($request)
            : null;

        if (is_array($productPayload) && $this->product !== null) {
            $mediaResolver = app(CustomerProductMediaResolver::class);
            $variant = $this->relationLoaded('variant') ? $this->variant : null;
            $productPayload['primary_image'] = $mediaResolver->resolvePrimary($this->product, $variant);
            $productPayload['images'] = $mediaResolver->resolveGallery($this->product, $variant);
        }

        $variantResource = null;
        if ($this->relationLoaded('variant') && $this->variant !== null) {
            // Media-only loads must not restore full variant attributes/presentation.
            $hasVariantPresentation = $this->variant->relationLoaded('catalogAttributeValues')
                || $this->variant->relationLoaded('attributeValues');
            $variantResource = $hasVariantPresentation
                ? new CustomerProductVariantResource($this->variant)
                : new CustomerProductListingVariantResource($this->variant);
        }

        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'product_variant_id' => $this->product_variant_id,
            'quantity' => $this->quantity,
            'unit_price' => $this->unit_price,
            'price_snapshot' => $this->price_snapshot ?? $this->unit_price,
            'currency' => $this->currency ?? 'TZS',
            'available_stock' => $available,
            'subtotal' => $this->subtotal(),
            'shipping_method' => $this->shipping_method?->value,
            'shipping_price' => $this->shipping_price,
            'estimated_delivery_days' => $this->estimated_delivery_days,
            'estimated_min_days' => $this->estimated_min_days,
            'estimated_max_days' => $this->estimated_max_days,
            'product' => $productPayload,
            'variant' => $variantResource,
            'volume_pricing' => $this->volumePricing(),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function volumePricing(): ?array
    {
        $product = $this->relationLoaded('product') ? $this->product : null;
        if ($product === null) {
            return null;
        }

        $variant = $this->relationLoaded('variant') ? $this->variant : null;
        $siblings = $this->relationLoaded('cart') && $this->cart !== null && $this->cart->relationLoaded('items')
            ? $this->cart->items
            : collect([$this->resource]);

        $eligible = max(1, CartProductPricingQuantity::forProduct($siblings, (string) $this->product_id));
        $catalog = $variant !== null
            ? app(CommercePricingResolver::class)->resolveVariantProductPrice(
                $variant,
                new CommercePricingContext(allowLegacyVariantFallback: true),
                $product,
            )
            : app(CommercePricingResolver::class)->resolveSimpleProductPrice(
                $product,
                new CommercePricingContext(allowLegacyVariantFallback: true),
            );

        if (! $catalog->resolved) {
            return null;
        }

        return app(PresentVolumePricing::class)->present(
            $product,
            $variant,
            $eligible,
            $catalog->unitPrice,
            (string) ($this->price_snapshot ?? $this->unit_price),
            max(1, (int) $this->quantity),
            (string) ($this->currency ?? 'TZS'),
        )?->toArray();
    }
}
