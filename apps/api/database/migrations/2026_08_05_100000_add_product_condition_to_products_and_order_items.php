<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (! Schema::hasColumn('products', 'product_condition')) {
                $table->string('product_condition', 32)->nullable()->after('catalog_product_type_id');
                $table->index('product_condition');
            }
        });

        Schema::table('order_items', function (Blueprint $table) {
            if (! Schema::hasColumn('order_items', 'product_condition_snapshot')) {
                $table->string('product_condition_snapshot', 32)->nullable()->after('brand_name_snapshot');
            }
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (Schema::hasColumn('products', 'product_condition')) {
                $table->dropIndex(['product_condition']);
                $table->dropColumn('product_condition');
            }
        });

        Schema::table('order_items', function (Blueprint $table) {
            if (Schema::hasColumn('order_items', 'product_condition_snapshot')) {
                $table->dropColumn('product_condition_snapshot');
            }
        });
    }
};
