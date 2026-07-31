<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('order_items')) {
            return;
        }

        Schema::table('order_items', function (Blueprint $table) {
            if (! Schema::hasColumn('order_items', 'barcode_snapshot')) {
                $table->string('barcode_snapshot')->nullable()->after('variant_sku_snapshot');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('order_items') || ! Schema::hasColumn('order_items', 'barcode_snapshot')) {
            return;
        }

        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn('barcode_snapshot');
        });
    }
};
