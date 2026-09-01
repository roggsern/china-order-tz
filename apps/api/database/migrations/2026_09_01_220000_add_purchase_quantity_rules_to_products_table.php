<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->unsignedInteger('minimum_order_quantity')->nullable()->after('pricing_model');
            $table->unsignedInteger('order_increment')->nullable()->after('minimum_order_quantity');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['minimum_order_quantity', 'order_increment']);
        });
    }
};
