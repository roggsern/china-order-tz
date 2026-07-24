<?php

namespace App\Services\Catalog;

use App\Enums\CommerceChannelCode;
use App\Enums\ProductLifecycleStatus;
use App\Models\CommerceChannel;
use App\Models\Product;
use App\Services\ProductShipping\ProductShippingOptionEngine;
use Illuminate\Database\Eloquent\Builder;

class ShippingOptionsBackfillAuditor
{
    public function __construct(
        private readonly ProductShippingOptionEngine $shippingOptionEngine,
    ) {}

    /**
     * @return array{
     *     china_channel_id: string|null,
     *     total_eligible: int,
     *     active: int,
     *     out_of_stock: int,
     *     draft: int,
     *     archived: int,
     *     other_lifecycle: int,
     *     products: list<array{
     *         id: string,
     *         name: string,
     *         slug: string,
     *         lifecycle_status: string,
     *         air_shipping_price: string|null,
     *         sea_shipping_price: string|null,
     *         shipping_options_count: int,
     *         available_priced_options_count: int,
     *         planned_modes: list<string>,
     *     }>
     * }
     */
    public function audit(): array
    {
        $chinaChannelId = CommerceChannel::query()
            ->where('code', CommerceChannelCode::ChinaImport->value)
            ->value('id');

        $products = $this->eligibleProductsQuery(is_string($chinaChannelId) ? $chinaChannelId : null)
            ->withCount([
                'shippingOptions as shipping_options_count',
                'shippingOptions as available_priced_options_count' => fn (Builder $query) => $query
                    ->available()
                    ->where('price', '>', 0),
            ])
            ->orderBy('name')
            ->get();

        $lifecycleCounts = [
            ProductLifecycleStatus::Active->value => 0,
            ProductLifecycleStatus::OutOfStock->value => 0,
            ProductLifecycleStatus::Draft->value => 0,
            ProductLifecycleStatus::Archived->value => 0,
            'other' => 0,
        ];

        $rows = $products->map(function (Product $product) use (&$lifecycleCounts) {
            $lifecycle = ProductLifecycleStatus::tryFromMixed($product->lifecycle_status);
            $key = $lifecycle?->value ?? 'other';
            if (! array_key_exists($key, $lifecycleCounts)) {
                $key = 'other';
            }
            $lifecycleCounts[$key]++;

            $plannedModes = array_map(
                fn (array $row) => (string) $row['transport_mode'],
                $this->buildLegacyRows($product),
            );

            return [
                'id' => $product->id,
                'name' => $product->name,
                'slug' => $product->slug,
                'lifecycle_status' => $lifecycle?->value ?? (string) $product->lifecycle_status,
                'air_shipping_price' => $this->formatMoney($product->air_shipping_price),
                'sea_shipping_price' => $this->formatMoney($product->sea_shipping_price),
                'shipping_options_count' => (int) ($product->shipping_options_count ?? 0),
                'available_priced_options_count' => (int) ($product->available_priced_options_count ?? 0),
                'planned_modes' => $plannedModes,
            ];
        })->values()->all();

        return [
            'china_channel_id' => is_string($chinaChannelId) ? $chinaChannelId : null,
            'total_eligible' => count($rows),
            'active' => $lifecycleCounts[ProductLifecycleStatus::Active->value],
            'out_of_stock' => $lifecycleCounts[ProductLifecycleStatus::OutOfStock->value],
            'draft' => $lifecycleCounts[ProductLifecycleStatus::Draft->value],
            'archived' => $lifecycleCounts[ProductLifecycleStatus::Archived->value],
            'other_lifecycle' => $lifecycleCounts['other'],
            'products' => $rows,
        ];
    }

    public function eligibleProductsQuery(?string $chinaChannelId): Builder
    {
        return Product::query()
            ->when(
                filled($chinaChannelId),
                fn (Builder $query) => $query->where('commerce_channel_id', $chinaChannelId),
                fn (Builder $query) => $query->whereRaw('1 = 0'),
            )
            ->where(function (Builder $query): void {
                $query->where(function (Builder $legacy): void {
                    $legacy->whereNotNull('air_shipping_price')
                        ->where('air_shipping_price', '>', 0);
                })->orWhere(function (Builder $legacy): void {
                    $legacy->whereNotNull('sea_shipping_price')
                        ->where('sea_shipping_price', '>', 0);
                });
            })
            ->where(function (Builder $query): void {
                $query->whereDoesntHave('shippingOptions')
                    ->orWhereDoesntHave('shippingOptions', function (Builder $options): void {
                        $options->available()->where('price', '>', 0);
                    });
            });
    }

    /**
     * @return list<array{
     *     transport_mode: string,
     *     price: float|int|string,
     *     currency: string,
     *     is_available: bool,
     *     sort_order: int
     * }>
     */
    public function buildLegacyRows(Product $product): array
    {
        $rows = [];

        if ($product->air_shipping_price !== null && (float) $product->air_shipping_price > 0) {
            $rows[] = [
                'transport_mode' => 'air',
                'price' => $product->air_shipping_price,
                'currency' => 'TZS',
                'is_available' => true,
                'sort_order' => 0,
            ];
        }

        if ($product->sea_shipping_price !== null && (float) $product->sea_shipping_price > 0) {
            $rows[] = [
                'transport_mode' => 'sea',
                'price' => $product->sea_shipping_price,
                'currency' => 'TZS',
                'is_available' => true,
                'sort_order' => 1,
            ];
        }

        return $rows;
    }

    public function isEligible(Product $product): bool
    {
        if ($this->shippingOptionEngine->hasPublishableShippingOption($product)) {
            return false;
        }

        if ($this->buildLegacyRows($product) === []) {
            return false;
        }

        $chinaChannelId = CommerceChannel::query()
            ->where('code', CommerceChannelCode::ChinaImport->value)
            ->value('id');

        if (! filled($chinaChannelId) || $product->commerce_channel_id !== $chinaChannelId) {
            return false;
        }

        return true;
    }

    private function formatMoney(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return number_format((float) $value, 2, '.', '');
    }
}
