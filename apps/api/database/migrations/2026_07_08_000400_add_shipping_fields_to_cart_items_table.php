<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cart_items', function (Blueprint $table) {
            $table->string('shipping_method')->nullable()->after('unit_price');
            $table->decimal('shipping_price', 12, 2)->nullable()->after('shipping_method');
        });
    }

    public function down(): void
    {
        Schema::table('cart_items', function (Blueprint $table) {
            $table->dropColumn(['shipping_method', 'shipping_price']);
        });
    }
};
