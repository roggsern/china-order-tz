<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ResetDemoCatalog extends Command
{
    protected $signature = 'catalog:reset-demo';

    protected $description = 'Remove demo catalog products and related data for clean UAT testing';

    public function handle(): int
    {
        if (! $this->confirm('This will delete all demo products, variants and related catalog data. Continue?')) {
            $this->info('Cancelled.');
            return self::SUCCESS;
        }

        DB::transaction(function () {

            // Customer/product interactions
            DB::table('cart_items')->delete();
            DB::table('wishlist_items')->delete();
            DB::table('reviews')->delete();

            // Variant related data
            DB::table('variant_prices')->delete();
            DB::table('variant_inventories')->delete();
            DB::table('inventory_stock_movements')->delete();
            DB::table('inventory_count_lines')->delete();
            DB::table('inventory_logs')->delete();

            DB::table('purchase_order_items')->delete();

            DB::table('supplier_products')->delete();
            DB::table('supplier_cost_histories')->delete();

            DB::table('product_variant_attribute_values')->delete();
            DB::table('product_variant_attribute_value')->delete();

            // Product related data
            DB::table('product_media')->delete();
            DB::table('product_images')->delete();

            DB::table('product_embeddings')->delete();

            DB::table('product_shipping_options')->delete();

            DB::table('configuration_price_tiers')->delete();

            DB::table('catalog_product_attribute_values')->delete();
            DB::table('attribute_dependencies')->delete();

            DB::table('loyalty_earn_rules')->delete();
            DB::table('loyalty_rewards')->delete();

            DB::table('category_product')->delete();

            // Remove variants before products
            DB::table('product_variants')->delete();

            // Finally remove products
            DB::table('products')->delete();
        });

        $this->info('Demo catalog reset completed.');

        return self::SUCCESS;
    }
}