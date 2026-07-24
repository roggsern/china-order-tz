<?php

namespace App\Services\Catalog;

use App\Enums\CommerceChannelCode;
use App\Enums\ProductLifecycleStatus;
use App\Models\CommerceChannel;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class TzLocalStoreOwnershipAuditor
{
    /**
     * @return array{
     *     tz_channel_id: string|null,
     *     total_affected: int,
     *     active: int,
     *     out_of_stock: int,
     *     draft: int,
     *     archived: int,
     *     other_lifecycle: int,
     *     auto_assignable_from_category: int,
     *     requires_manual_assignment: int,
     *     products: list<array{
     *         id: string,
     *         name: string,
     *         slug: string,
     *         lifecycle_status: string,
     *         is_active: bool,
     *         category_store_id: string|null,
     *         suggested_store_id: string|null,
     *         auto_assign_eligible: bool,
     *         manual_assignment_required: bool,
     *     }>
     * }
     */
    public function audit(): array
    {
        $tzChannelId = CommerceChannel::query()
            ->where('code', CommerceChannelCode::TzLocal->value)
            ->value('id');

        $products = $this->orphanTzLocalProductsQuery($tzChannelId)
            ->with(['category:id,name,store_id'])
            ->orderBy('name')
            ->get();

        $rows = $products->map(fn (Product $product) => $this->mapProductRow($product))->values()->all();

        $lifecycleCounts = $this->countByLifecycle($products);

        $autoAssignable = collect($rows)->where('auto_assign_eligible', true)->count();
        $manualRequired = collect($rows)->where('manual_assignment_required', true)->count();

        return [
            'tz_channel_id' => is_string($tzChannelId) ? $tzChannelId : null,
            'total_affected' => count($rows),
            'active' => $lifecycleCounts[ProductLifecycleStatus::Active->value] ?? 0,
            'out_of_stock' => $lifecycleCounts[ProductLifecycleStatus::OutOfStock->value] ?? 0,
            'draft' => $lifecycleCounts[ProductLifecycleStatus::Draft->value] ?? 0,
            'archived' => $lifecycleCounts[ProductLifecycleStatus::Archived->value] ?? 0,
            'other_lifecycle' => $lifecycleCounts['other'] ?? 0,
            'auto_assignable_from_category' => $autoAssignable,
            'requires_manual_assignment' => $manualRequired,
            'products' => $rows,
        ];
    }

    public function orphanTzLocalProductsQuery(?string $tzChannelId): Builder
    {
        return Product::query()
            ->whereNull('store_id')
            ->where(function (Builder $query) use ($tzChannelId): void {
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

    /**
     * @return array<string, int>
     */
    private function countByLifecycle(Collection $products): array
    {
        $counts = [
            ProductLifecycleStatus::Active->value => 0,
            ProductLifecycleStatus::OutOfStock->value => 0,
            ProductLifecycleStatus::Draft->value => 0,
            ProductLifecycleStatus::Archived->value => 0,
            'other' => 0,
        ];

        foreach ($products as $product) {
            $status = ProductLifecycleStatus::tryFromMixed($product->lifecycle_status);
            $key = $status?->value ?? 'other';
            if (! array_key_exists($key, $counts)) {
                $key = 'other';
            }
            $counts[$key]++;
        }

        return $counts;
    }

    /**
     * @return array{
     *     id: string,
     *     name: string,
     *     slug: string,
     *     lifecycle_status: string,
     *     is_active: bool,
     *     category_store_id: string|null,
     *     suggested_store_id: string|null,
     *     auto_assign_eligible: bool,
     *     manual_assignment_required: bool,
     * }
     */
    private function mapProductRow(Product $product): array
    {
        $lifecycle = ProductLifecycleStatus::tryFromMixed($product->lifecycle_status);
        $categoryStoreId = $product->category?->store_id;
        $suggestedStoreId = filled($categoryStoreId) ? (string) $categoryStoreId : null;

        $listed = in_array($lifecycle, [ProductLifecycleStatus::Active, ProductLifecycleStatus::OutOfStock], true);
        $autoEligible = ! $listed && filled($suggestedStoreId);
        $manualRequired = $listed || ! filled($suggestedStoreId);

        return [
            'id' => $product->id,
            'name' => $product->name,
            'slug' => $product->slug,
            'lifecycle_status' => $lifecycle?->value ?? (string) $product->lifecycle_status,
            'is_active' => (bool) $product->is_active,
            'category_store_id' => $suggestedStoreId,
            'suggested_store_id' => $suggestedStoreId,
            'auto_assign_eligible' => $autoEligible,
            'manual_assignment_required' => $manualRequired,
        ];
    }
}
