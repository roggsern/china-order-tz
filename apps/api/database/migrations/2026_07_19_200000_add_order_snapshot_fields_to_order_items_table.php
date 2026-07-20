<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Order Snapshot Engine — extend order_items with immutable commercial snapshots.
 * No duplicate snapshot tables; historical truth lives on the line item.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('order_items')) {
            return;
        }

        Schema::table('order_items', function (Blueprint $table) {
            if (! Schema::hasColumn('order_items', 'product_slug_snapshot')) {
                $table->string('product_slug_snapshot')->nullable()->after('product_name_snapshot');
            }
            if (! Schema::hasColumn('order_items', 'brand_name_snapshot')) {
                $table->string('brand_name_snapshot')->nullable()->after('sku_snapshot');
            }
            if (! Schema::hasColumn('order_items', 'variant_sku_snapshot')) {
                $table->string('variant_sku_snapshot')->nullable()->after('variant_name_snapshot');
            }
            if (! Schema::hasColumn('order_items', 'currency_snapshot')) {
                $table->string('currency_snapshot', 3)->nullable()->after('currency');
            }
            if (! Schema::hasColumn('order_items', 'unit_price_snapshot')) {
                $table->decimal('unit_price_snapshot', 12, 2)->nullable()->after('unit_price');
            }
            if (! Schema::hasColumn('order_items', 'shipping_mode_snapshot')) {
                $table->string('shipping_mode_snapshot')->nullable()->after('shipping_method');
            }
            if (! Schema::hasColumn('order_items', 'shipping_price_snapshot')) {
                $table->decimal('shipping_price_snapshot', 12, 2)->nullable()->after('shipping_price');
            }
            if (! Schema::hasColumn('order_items', 'shipping_notes_snapshot')) {
                $table->text('shipping_notes_snapshot')->nullable()->after('shipping_price_snapshot');
            }
            if (! Schema::hasColumn('order_items', 'attributes_snapshot')) {
                $table->json('attributes_snapshot')->nullable()->after('shipping_notes_snapshot');
            }
            if (! Schema::hasColumn('order_items', 'product_image_snapshot')) {
                $table->string('product_image_snapshot')->nullable()->after('image_snapshot');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('order_items')) {
            return;
        }

        Schema::table('order_items', function (Blueprint $table) {
            foreach ([
                'product_slug_snapshot',
                'brand_name_snapshot',
                'variant_sku_snapshot',
                'currency_snapshot',
                'unit_price_snapshot',
                'shipping_mode_snapshot',
                'shipping_price_snapshot',
                'shipping_notes_snapshot',
                'attributes_snapshot',
                'product_image_snapshot',
            ] as $column) {
                if (Schema::hasColumn('order_items', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
