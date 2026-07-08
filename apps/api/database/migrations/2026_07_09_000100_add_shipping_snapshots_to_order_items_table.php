<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->string('shipping_method')->nullable()->after('total_price');
            $table->decimal('shipping_price', 12, 2)->nullable()->after('shipping_method');
            $table->decimal('shipping_subtotal', 12, 2)->nullable()->after('shipping_price');
            $table->string('delivery_status')->nullable()->after('shipping_subtotal');
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn([
                'shipping_method',
                'shipping_price',
                'shipping_subtotal',
                'delivery_status',
            ]);
        });
    }
};
