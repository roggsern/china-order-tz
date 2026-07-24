<?php

namespace App\Services\Catalog;

use App\Enums\CommerceChannelCode;
use App\Enums\ProductLifecycleStatus;
use App\Models\CommerceChannel;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;

class ShippingConsistencyAuditor
{
    /** Legacy typo variants sometimes found on older rows. */
    private const CHINA_FULFILLMENT_SOURCES = [
        'imported_from_china',
        'buy_from_china',
    ];

    /**
     * @return array{
     *     china_channel_id: string|null,
     *     tz_channel_id: string|null,
     *     china_import_missing_shipping: array{
     *         total: int,
     *         products: list<array{
     *             id: string,
     *             name: string,
     *             slug: string,
     *             lifecycle_status: string,
     *             air_shipping_price: string|null,
     *             sea_shipping_price: string|null,
     *             shipping_options_count: int,
     *             available_priced_options_count: int,
     *         }>
     *     },
     *     tz_local_invalid_freight: array{
     *         total: int,
     *         products: list<array{
     *             id: string,
     *             name: string,
     *             slug: string,
     *             lifecycle_status: string,
     *             air_shipping_price: string|null,
     *             sea_shipping_price: string|null,
     *             shipping_options_count: int,
     *             issues: list<string>,
     *         }>
     *     },
     *     legacy_missing_commerce_channel: array{
     *         total: int,
     *         fulfillment_sources: list<string>,
     *         products: list<array{
     *             id: string,
     *             name: string,
     *             slug: string,
     *             lifecycle_status: string,
     *             fulfillment_source: string|null,
     *         }>
     *     }
     * }
     */
    public function audit(): array
    {
        $chinaChannelId = $this->resolveChannelId(CommerceChannelCode::ChinaImport);
        $tzChannelId = $this->resolveChannelId(CommerceChannelCode::TzLocal);

        $chinaMissing = $this->chinaImportMissingShippingQuery($chinaChannelId)
            ->withCount([
                'shippingOptions as shipping_options_count',
                'shippingOptions as available_priced_options_count' => fn (Builder $query) => $query
                    ->available()
                    ->where('price', '>', 0),
            ])
            ->orderBy('name')
            ->get()
            ->map(fn (Product $product) => $this->mapChinaMissingShippingRow($product))
            ->values()
            ->all();

        $tzInvalid = $this->tzLocalInvalidFreightQuery($tzChannelId)
            ->withCount('shippingOptions as shipping_options_count')
            ->orderBy('name')
            ->get()
            ->map(fn (Product $product) => $this->mapTzInvalidFreightRow($product))
            ->values()
            ->all();

        $legacyMissingChannel = $this->legacyMissingCommerceChannelQuery()
            ->orderBy('name')
            ->get();

        $legacySources = $legacyMissingChannel
            ->pluck('fulfillment_source')
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->all();

        return [
            'china_channel_id' => $chinaChannelId,
            'tz_channel_id' => $tzChannelId,
            'china_import_missing_shipping' => [
                'total' => count($chinaMissing),
                'products' => $chinaMissing,
            ],
            'tz_local_invalid_freight' => [
                'total' => count($tzInvalid),
                'products' => $tzInvalid,
            ],
            'legacy_missing_commerce_channel' => [
                'total' => $legacyMissingChannel->count(),
                'fulfillment_sources' => $legacySources,
                'products' => $legacyMissingChannel
                    ->map(fn (Product $product) => $this->mapLegacyMissingChannelRow($product))
                    ->values()
                    ->all(),
            ],
        ];
    }

    public function chinaImportMissingShippingQuery(?string $chinaChannelId): Builder
    {
        return $this->chinaImportProductsQuery($chinaChannelId)
            ->where(function (Builder $query): void {
                $query->whereDoesntHave('shippingOptions')
                    ->orWhereDoesntHave('shippingOptions', function (Builder $options): void {
                        $options->available()->where('price', '>', 0);
                    });
            });
    }

    public function tzLocalInvalidFreightQuery(?string $tzChannelId): Builder
    {
        return $this->tzLocalProductsQuery($tzChannelId)
            ->where(function (Builder $query): void {
                $query->where(function (Builder $legacy): void {
                    $legacy->whereNotNull('air_shipping_price')
                        ->where('air_shipping_price', '>', 0);
                })->orWhere(function (Builder $legacy): void {
                    $legacy->whereNotNull('sea_shipping_price')
                        ->where('sea_shipping_price', '>', 0);
                })->orWhereHas('shippingOptions');
            });
    }

    public function legacyMissingCommerceChannelQuery(): Builder
    {
        return Product::query()
            ->whereNull('commerce_channel_id')
            ->where(function (Builder $query): void {
                foreach (self::CHINA_FULFILLMENT_SOURCES as $index => $source) {
                    if ($index === 0) {
                        $query->where('fulfillment_source', $source);
                    } else {
                        $query->orWhere('fulfillment_source', $source);
                    }
                }
            });
    }

    public function chinaImportProductsQuery(?string $chinaChannelId): Builder
    {
        return Product::query()->where(function (Builder $query) use ($chinaChannelId): void {
            if (filled($chinaChannelId)) {
                $query->where('commerce_channel_id', $chinaChannelId);
            }

            $query->orWhere(function (Builder $legacy): void {
                $legacy->whereNull('commerce_channel_id')
                    ->where(
                        'fulfillment_source',
                        CommerceChannelCode::ChinaImport->fulfillmentSource(),
                    );
            });
        });
    }

    public function tzLocalProductsQuery(?string $tzChannelId): Builder
    {
        return Product::query()->where(function (Builder $query) use ($tzChannelId): void {
            if (filled($tzChannelId)) {
                $query->where('commerce_channel_id', $tzChannelId);
            }

            $query->orWhere(function (Builder $legacy): void {
                $legacy->whereNull('commerce_channel_id')
                    ->where(
                        'fulfillment_source',
                        CommerceChannelCode::TzLocal->fulfillmentSource(),
                    );
            });
        });
    }

    private function resolveChannelId(CommerceChannelCode $code): ?string
    {
        $id = CommerceChannel::query()->where('code', $code->value)->value('id');

        return is_string($id) ? $id : null;
    }

    /**
     * @return array{
     *     id: string,
     *     name: string,
     *     slug: string,
     *     lifecycle_status: string,
     *     air_shipping_price: string|null,
     *     sea_shipping_price: string|null,
     *     shipping_options_count: int,
     *     available_priced_options_count: int,
     * }
     */
    private function mapChinaMissingShippingRow(Product $product): array
    {
        return [
            'id' => $product->id,
            'name' => $product->name,
            'slug' => $product->slug,
            'lifecycle_status' => $this->formatLifecycle($product),
            'air_shipping_price' => $this->formatMoney($product->air_shipping_price),
            'sea_shipping_price' => $this->formatMoney($product->sea_shipping_price),
            'shipping_options_count' => (int) ($product->shipping_options_count ?? 0),
            'available_priced_options_count' => (int) ($product->available_priced_options_count ?? 0),
        ];
    }

    /**
     * @return array{
     *     id: string,
     *     name: string,
     *     slug: string,
     *     lifecycle_status: string,
     *     air_shipping_price: string|null,
     *     sea_shipping_price: string|null,
     *     shipping_options_count: int,
     *     issues: list<string>,
     * }
     */
    private function mapTzInvalidFreightRow(Product $product): array
    {
        $issues = [];

        if ($product->air_shipping_price !== null && (float) $product->air_shipping_price > 0) {
            $issues[] = 'air_shipping_price';
        }

        if ($product->sea_shipping_price !== null && (float) $product->sea_shipping_price > 0) {
            $issues[] = 'sea_shipping_price';
        }

        if ((int) ($product->shipping_options_count ?? 0) > 0) {
            $issues[] = 'product_shipping_options';
        }

        return [
            'id' => $product->id,
            'name' => $product->name,
            'slug' => $product->slug,
            'lifecycle_status' => $this->formatLifecycle($product),
            'air_shipping_price' => $this->formatMoney($product->air_shipping_price),
            'sea_shipping_price' => $this->formatMoney($product->sea_shipping_price),
            'shipping_options_count' => (int) ($product->shipping_options_count ?? 0),
            'issues' => $issues,
        ];
    }

    /**
     * @return array{
     *     id: string,
     *     name: string,
     *     slug: string,
     *     lifecycle_status: string,
     *     fulfillment_source: string|null,
     * }
     */
    private function mapLegacyMissingChannelRow(Product $product): array
    {
        return [
            'id' => $product->id,
            'name' => $product->name,
            'slug' => $product->slug,
            'lifecycle_status' => $this->formatLifecycle($product),
            'fulfillment_source' => $product->fulfillment_source,
        ];
    }

    private function formatLifecycle(Product $product): string
    {
        return ProductLifecycleStatus::tryFromMixed($product->lifecycle_status)?->value
            ?? (string) $product->lifecycle_status;
    }

    private function formatMoney(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return number_format((float) $value, 2, '.', '');
    }
}
