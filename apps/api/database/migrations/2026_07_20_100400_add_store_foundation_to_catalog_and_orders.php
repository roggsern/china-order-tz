<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->foreignUuid('store_id')
                ->nullable()
                ->after('commerce_channel_id')
                ->constrained('stores')
                ->nullOnDelete();
            $table->index('store_id');
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->foreignUuid('store_id')
                ->nullable()
                ->after('department_id')
                ->constrained('stores')
                ->nullOnDelete();
            $table->index('store_id');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->foreignUuid('store_id')
                ->nullable()
                ->after('user_id')
                ->constrained('stores')
                ->nullOnDelete();
            $table->string('sales_origin', 32)->default('online')->after('store_id');
            $table->foreignUuid('pos_session_id')
                ->nullable()
                ->after('checkout_session_id')
                ->constrained('pos_sessions')
                ->nullOnDelete();
            $table->index(['store_id', 'sales_origin']);
        });

        // Walk-in POS: orders without a customer account.
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
        });
        Schema::table('orders', function (Blueprint $table) {
            $table->uuid('user_id')->nullable()->change();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
        });

        Schema::table('variant_inventories', function (Blueprint $table) {
            $table->foreignUuid('inventory_location_id')
                ->nullable()
                ->after('product_variant_id')
                ->constrained('inventory_locations')
                ->nullOnDelete();
            $table->index('inventory_location_id');
        });

        // Walk-in POS payments may have no customer user.
        Schema::table('payments', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
        });
        Schema::table('payments', function (Blueprint $table) {
            $table->uuid('user_id')->nullable()->change();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
        });
        Schema::table('payments', function (Blueprint $table) {
            $table->uuid('user_id')->nullable(false)->change();
            $table->foreign('user_id')->references('id')->on('users')->restrictOnDelete();
        });

        Schema::table('variant_inventories', function (Blueprint $table) {
            $table->dropConstrainedForeignId('inventory_location_id');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
        });
        Schema::table('orders', function (Blueprint $table) {
            $table->uuid('user_id')->nullable(false)->change();
            $table->foreign('user_id')->references('id')->on('users')->restrictOnDelete();
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('pos_session_id');
            $table->dropConstrainedForeignId('store_id');
            $table->dropColumn('sales_origin');
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->dropConstrainedForeignId('store_id');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropConstrainedForeignId('store_id');
        });
    }
};
