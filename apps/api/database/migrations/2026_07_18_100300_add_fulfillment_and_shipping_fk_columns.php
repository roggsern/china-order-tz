<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * MASTER_SPECIFICATION: products.fulfillment_source;
 * cart_items / order_items shipping_method_id, shipping_cost, estimated_delivery_days.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('fulfillment_source')
                ->default('imported_from_china')
                ->after('supplier_id')
                ->index();
        });

        if (Schema::hasTable('suppliers')) {
            DB::table('products')
                ->join('suppliers', 'products.supplier_id', '=', 'suppliers.id')
                ->whereRaw('LOWER(suppliers.country) != ?', ['china'])
                ->update(['products.fulfillment_source' => 'buy_from_tz']);
        }

        Schema::table('cart_items', function (Blueprint $table) {
            $table->foreignUuid('shipping_method_id')
                ->nullable()
                ->after('unit_price')
                ->constrained('shipping_methods')
                ->nullOnDelete();
            $table->decimal('shipping_cost', 12, 2)->nullable()->after('shipping_method_id');
            $table->unsignedInteger('estimated_delivery_days')->nullable()->after('shipping_cost');
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->foreignUuid('shipping_method_id')
                ->nullable()
                ->after('total_price')
                ->constrained('shipping_methods')
                ->nullOnDelete();
            $table->decimal('shipping_cost', 12, 2)->nullable()->after('shipping_method_id');
            $table->unsignedInteger('estimated_delivery_days')->nullable()->after('shipping_cost');
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('shipping_method_id');
            $table->dropColumn(['shipping_cost', 'estimated_delivery_days']);
        });

        Schema::table('cart_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('shipping_method_id');
            $table->dropColumn(['shipping_cost', 'estimated_delivery_days']);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('fulfillment_source');
        });
    }
};
