<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TASK 044 — BUY FROM TZ storefront visibility flags on existing stores.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->boolean('storefront_enabled')->default(true)->after('is_active');
            $table->boolean('storefront_visible')->default(true)->after('storefront_enabled');
            $table->boolean('storefront_featured')->default(false)->after('storefront_visible');
            $table->unsignedInteger('storefront_sort_order')->nullable()->after('storefront_featured');

            $table->index(
                ['is_active', 'storefront_enabled', 'storefront_visible', 'storefront_sort_order'],
                'stores_storefront_visibility_idx',
            );
        });
    }

    public function down(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->dropIndex('stores_storefront_visibility_idx');
            $table->dropColumn([
                'storefront_enabled',
                'storefront_visible',
                'storefront_featured',
                'storefront_sort_order',
            ]);
        });
    }
};
