<?php

namespace App\Services\Catalog;

use App\Enums\CommerceChannelCode;
use App\Enums\ProductLifecycleStatus;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;

class CommerceChannelAuditor
{
    /**
     * @return array{
     *     china_channel_id: string|null,
     *     total_affected: int,
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
     *         fulfillment_source: string|null,
     *     }>
     * }
     */
    public function audit(): array
    {
        $resolvedChinaChannelId = \App\Models\CommerceChannel::query()
            ->where('code', CommerceChannelCode::ChinaImport->value)
            ->value('id');

        $products = $this->missingChinaChannelQuery()
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

            return [
                'id' => $product->id,
                'name' => $product->name,
                'slug' => $product->slug,
                'lifecycle_status' => $lifecycle?->value ?? (string) $product->lifecycle_status,
                'fulfillment_source' => $product->fulfillment_source,
            ];
        })->values()->all();

        return [
            'china_channel_id' => is_string($resolvedChinaChannelId) ? $resolvedChinaChannelId : null,
            'total_affected' => count($rows),
            'active' => $lifecycleCounts[ProductLifecycleStatus::Active->value],
            'out_of_stock' => $lifecycleCounts[ProductLifecycleStatus::OutOfStock->value],
            'draft' => $lifecycleCounts[ProductLifecycleStatus::Draft->value],
            'archived' => $lifecycleCounts[ProductLifecycleStatus::Archived->value],
            'other_lifecycle' => $lifecycleCounts['other'],
            'products' => $rows,
        ];
    }

    public function missingChinaChannelQuery(): Builder
    {
        return Product::query()
            ->whereNull('commerce_channel_id')
            ->where(
                'fulfillment_source',
                CommerceChannelCode::ChinaImport->fulfillmentSource(),
            );
    }
}
