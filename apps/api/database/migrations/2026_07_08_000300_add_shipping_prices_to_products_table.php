<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->decimal('air_shipping_price', 12, 2)->nullable()->after('price');
            $table->decimal('sea_shipping_price', 12, 2)->nullable()->after('air_shipping_price');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['air_shipping_price', 'sea_shipping_price']);
        });
    }
};
