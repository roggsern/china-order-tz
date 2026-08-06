<?php

namespace App\Services\AdminProducts;

use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductMedia;
use App\Models\ProductVariant;
use App\Models\Review;
use App\Models\VariantPrice;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Read-only force-delete eligibility for soft-deleted catalog products.
 *
 * Order snapshots remain readable when deletion is blocked or when FKs null on delete;
 * we still treat live order_item references as protected business history.
 */
class ProductForceDeleteEligibilityService
{
    /**
     * @return array{
     *     can_force_delete: bool,
     *     confirmation_phrase: string,
     *     product: array{id: string, name: string, slug: string, deleted_at: string|null},
     *     blocking_dependencies: list<array{type: string, count: int, message: string}>,
     *     deletable_dependencies: array<string, int>
     * }
     */
    public function evaluate(Product $product): array
    {
        $variantIds = ProductVariant::withTrashed()
            ->where('product_id', $product->id)
            ->pluck('id')
            ->all();

        $blockers = [];

        $orderItemCount = OrderItem::query()
            ->where(function ($query) use ($product, $variantIds): void {
                $query->where('product_id', $product->id);
                if ($variantIds !== []) {
                    $query->orWhereIn('product_variant_id', $variantIds);
                }
            })
            ->count();

        if ($orderItemCount > 0) {
            $blockers[] = [
                'type' => 'order_items',
                'count' => $orderItemCount,
                'message' => 'This product appears in an existing order and cannot be permanently deleted.',
            ];
        }

        if (Schema::hasTable('purchase_order_items') && $variantIds !== []) {
            $poCount = (int) DB::table('purchase_order_items')
                ->whereIn('product_variant_id', $variantIds)
                ->count();
            if ($poCount > 0) {
                $blockers[] = [
                    'type' => 'purchase_order_items',
                    'count' => $poCount,
                    'message' => 'This product has purchase-order lines that must be retained.',
                ];
            }
        }

        if (Schema::hasTable('return_items') && $orderItemCount > 0) {
            $returnCount = (int) DB::table('return_items')
                ->whereIn('order_item_id', function ($query) use ($product, $variantIds): void {
                    $query->select('id')
                        ->from('order_items')
                        ->where(function ($inner) use ($product, $variantIds): void {
                            $inner->where('product_id', $product->id);
                            if ($variantIds !== []) {
                                $inner->orWhereIn('product_variant_id', $variantIds);
                            }
                        });
                })
                ->count();
            if ($returnCount > 0) {
                $blockers[] = [
                    'type' => 'return_items',
                    'count' => $returnCount,
                    'message' => 'This product is linked to return records.',
                ];
            }
        }

        if (Schema::hasTable('reviews')) {
            $reviewCount = Review::query()->where('product_id', $product->id)->count();
            if ($reviewCount > 0) {
                $blockers[] = [
                    'type' => 'reviews',
                    'count' => $reviewCount,
                    'message' => 'This product has customer reviews that must be retained.',
                ];
            }
        }

        $variantsCount = count($variantIds);
        $pricesCount = $variantIds === []
            ? 0
            : VariantPrice::query()->whereIn('product_variant_id', $variantIds)->count();
        $mediaCount = ProductMedia::withTrashed()->where('product_id', $product->id)->count();
        $imagesCount = ProductImage::withTrashed()->where('product_id', $product->id)->count();

        return [
            'can_force_delete' => $blockers === [],
            'confirmation_phrase' => $this->confirmationPhrase($product),
            'product' => [
                'id' => $product->id,
                'name' => (string) $product->name,
                'slug' => (string) $product->slug,
                'deleted_at' => $product->deleted_at?->toIso8601String(),
            ],
            'blocking_dependencies' => $blockers,
            'deletable_dependencies' => [
                'variants' => $variantsCount,
                'variant_prices' => $pricesCount,
                'product_media' => $mediaCount,
                'product_images' => $imagesCount,
            ],
        ];
    }

    public function confirmationPhrase(Product $product): string
    {
        $label = trim((string) $product->name);
        if ($label === '') {
            $label = trim((string) $product->slug) ?: 'PRODUCT';
        }

        return 'DELETE '.mb_strtoupper($label);
    }
}
