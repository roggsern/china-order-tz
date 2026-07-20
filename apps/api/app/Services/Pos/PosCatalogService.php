<?php

namespace App\Services\Pos;

use App\Enums\CommerceChannelCode;
use App\Enums\VariantPriceType;
use App\Models\InventoryLocation;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Store;
use App\Models\VariantInventory;
use App\Models\VariantPrice;
use App\Services\Stores\StoreService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Store-scoped TZ_LOCAL catalog for POS cashiers.
 * Prices from VariantPrice (Pricing Engine). Stock from store inventory location.
 */
class PosCatalogService
{
    public function __construct(
        private readonly StoreService $stores,
    ) {}

    /**
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    public function search(Store $store, ?string $query = null, int $perPage = 24): LengthAwarePaginator
    {
        $location = $this->stores->defaultLocation($store);
        $q = trim((string) $query);

        $productsQuery = Product::query()
            ->with([
                'category:id,name,slug',
                'variants' => fn ($v) => $v->where('is_active', true)->orderBy('sort_order'),
                'variants.prices',
                'variants.inventories' => fn ($i) => $i->where(function ($w) use ($location) {
                    $w->where('inventory_location_id', $location->id)
                        ->orWhere('warehouse_code', $location->code);
                }),
            ])
            ->where('store_id', $store->id)
            ->where('is_active', true)
            ->where('is_demo', false)
            ->whereHas('commerceChannel', fn ($c) => $c->where('code', CommerceChannelCode::TzLocal->value));

        if ($q !== '') {
            $productsQuery->where(function (Builder $builder) use ($q) {
                $builder->where('name', 'like', "%{$q}%")
                    ->orWhere('sku', 'like', "%{$q}%")
                    ->orWhereHas('variants', function (Builder $v) use ($q) {
                        $v->where('sku', 'like', "%{$q}%")
                            ->orWhere('barcode', 'like', "%{$q}%")
                            ->orWhere('name', 'like', "%{$q}%");
                    });
            });
        }

        $paginator = $productsQuery->orderBy('name')->paginate($perPage);

        $paginator->setCollection(
            $paginator->getCollection()->flatMap(
                fn (Product $product) => $this->mapProductRows($product, $location)
            )->values()
        );

        return $paginator;
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function mapProductRows(Product $product, InventoryLocation $location): Collection
    {
        $variants = $product->variants;
        if ($variants->isEmpty()) {
            return collect();
        }

        return $variants->map(function (ProductVariant $variant) use ($product, $location) {
            $price = $this->resolveRetailAmount($variant);
            $stock = $this->availableAtLocation($variant, $location);

            return [
                'product_id' => $product->id,
                'product_name' => $product->name,
                'product_sku' => $product->sku,
                'category' => $product->category?->only(['id', 'name', 'slug']),
                'product_variant_id' => $variant->id,
                'variant_name' => $variant->name,
                'variant_sku' => $variant->sku,
                'barcode' => $variant->barcode,
                'unit_price' => $price,
                'currency' => 'TZS',
                'available_stock' => $stock,
                'in_stock' => $stock > 0,
            ];
        })->filter(fn (array $row) => $row['unit_price'] !== null);
    }

    public function resolveRetailAmount(ProductVariant $variant): ?string
    {
        $retail = $variant->relationLoaded('prices')
            ? $variant->prices
                ->filter(fn (VariantPrice $p) => $p->is_active
                    && $p->price_type === VariantPriceType::Retail
                    && strtoupper((string) $p->currency) === 'TZS')
                ->sortBy('minimum_quantity')
                ->first()
            : $variant->retailPrice('TZS');

        if ($retail !== null) {
            return number_format((float) $retail->amount, 2, '.', '');
        }

        if ($variant->price !== null) {
            return number_format((float) $variant->price, 2, '.', '');
        }

        return null;
    }

    public function availableAtLocation(ProductVariant $variant, InventoryLocation $location): int
    {
        /** @var VariantInventory|null $inventory */
        $inventory = $variant->relationLoaded('inventories')
            ? $variant->inventories->first(fn (VariantInventory $row) => $row->inventory_location_id === $location->id
                || $row->warehouse_code === $location->code)
            : VariantInventory::query()
                ->where('product_variant_id', $variant->id)
                ->where(function ($q) use ($location) {
                    $q->where('inventory_location_id', $location->id)
                        ->orWhere('warehouse_code', $location->code);
                })
                ->where('is_active', true)
                ->first();

        return $inventory?->available() ?? 0;
    }
}
