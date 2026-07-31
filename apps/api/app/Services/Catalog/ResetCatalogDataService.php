<?php

namespace App\Services\Catalog;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Development-only cleanup for product catalog instance data.
 *
 * Preserves taxonomy, master attributes, stores, suppliers, and identity records.
 */
class ResetCatalogDataService
{
    /**
     * Child tables first — respects FK constraints without disabling checks.
     *
     * @var list<string>
     */
    private const DELETION_ORDER = [
        'review_images',
        'reviews',
        'notifications',
        'cart_items',
        'wishlist_items',
        'receiving_record_items',
        'supplier_cost_histories',
        'purchase_order_items',
        'inventory_count_lines',
        'inventory_stock_movements',
        'inventory_logs',
        'variant_inventories',
        'inventory',
        'variant_prices',
        'product_variant_attribute_values',
        'product_variant_attribute_value',
        'configuration_price_tiers',
        'product_channel_backfill_logs',
        'product_store_backfill_logs',
        'product_shipping_backfill_logs',
        'product_embeddings',
        'product_media',
        'product_images',
        'catalog_product_attribute_values',
        'product_shipping_options',
        'category_product',
        'supplier_products',
        'ai_recommendations',
        'product_variants',
        'products',
    ];

    /**
     * @return array{
     *     deleted: array<string, int>,
     *     remaining: array<string, int>
     * }
     */
    public function handle(): array
    {
        $deletedByTable = [];

        DB::transaction(function () use (&$deletedByTable): void {
            foreach (self::DELETION_ORDER as $table) {
                $deletedByTable[$table] = $this->deleteTable($table);
            }

            $deletedByTable['attribute_dependencies'] = $this->deleteProductScopedAttributeDependencies();
        });

        return [
            'deleted' => $this->summarizeDeleted($deletedByTable),
            'remaining' => $this->scanRemainingProductTables(),
        ];
    }

    /**
     * @param  array<string, int>  $deletedByTable
     * @return array<string, int>
     */
    private function summarizeDeleted(array $deletedByTable): array
    {
        return [
            'products' => $deletedByTable['products'] ?? 0,
            'variants' => $deletedByTable['product_variants'] ?? 0,
            'media' => ($deletedByTable['product_media'] ?? 0)
                + ($deletedByTable['product_images'] ?? 0),
            'prices' => ($deletedByTable['variant_prices'] ?? 0)
                + ($deletedByTable['configuration_price_tiers'] ?? 0),
            'inventory_records' => ($deletedByTable['inventory'] ?? 0)
                + ($deletedByTable['variant_inventories'] ?? 0)
                + ($deletedByTable['inventory_stock_movements'] ?? 0)
                + ($deletedByTable['inventory_logs'] ?? 0)
                + ($deletedByTable['inventory_count_lines'] ?? 0),
            'notifications' => $deletedByTable['notifications'] ?? 0,
            'reviews' => ($deletedByTable['reviews'] ?? 0)
                + ($deletedByTable['review_images'] ?? 0),
            'product_attribute_assignments' => ($deletedByTable['catalog_product_attribute_values'] ?? 0)
                + ($deletedByTable['product_variant_attribute_values'] ?? 0)
                + ($deletedByTable['product_variant_attribute_value'] ?? 0)
                + ($deletedByTable['attribute_dependencies'] ?? 0),
            'customer_product_links' => ($deletedByTable['cart_items'] ?? 0)
                + ($deletedByTable['wishlist_items'] ?? 0),
        ];
    }

    /**
     * @return array<string, int>
     */
    private function scanRemainingProductTables(): array
    {
        $remaining = [];

        foreach (['products', 'product_variants', 'product_media', 'variant_prices', 'inventory'] as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            $count = (int) DB::table($table)->count();
            if ($count > 0) {
                $remaining[$table] = $count;
            }
        }

        return $remaining;
    }

    private function deleteProductScopedAttributeDependencies(): int
    {
        if (! Schema::hasTable('attribute_dependencies')) {
            return 0;
        }

        return DB::table('attribute_dependencies')
            ->whereNotNull('product_id')
            ->delete();
    }

    private function deleteTable(string $table): int
    {
        if (! Schema::hasTable($table)) {
            return 0;
        }

        return DB::table($table)->delete();
    }
}
