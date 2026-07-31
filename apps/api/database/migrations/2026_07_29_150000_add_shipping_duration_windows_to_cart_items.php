<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Capture resolved duration windows on cart lines at shipping selection.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cart_items')) {
            return;
        }

        Schema::table('cart_items', function (Blueprint $table) {
            if (! Schema::hasColumn('cart_items', 'estimated_min_days')) {
                $table->unsignedInteger('estimated_min_days')->nullable()->after('estimated_delivery_days');
            }
            if (! Schema::hasColumn('cart_items', 'estimated_max_days')) {
                $table->unsignedInteger('estimated_max_days')->nullable()->after('estimated_min_days');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('cart_items')) {
            return;
        }

        Schema::table('cart_items', function (Blueprint $table) {
            if (Schema::hasColumn('cart_items', 'estimated_max_days')) {
                $table->dropColumn('estimated_max_days');
            }
            if (Schema::hasColumn('cart_items', 'estimated_min_days')) {
                $table->dropColumn('estimated_min_days');
            }
        });
    }
};
