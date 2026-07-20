<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->boolean('is_demo')->default(false)->after('currency');
            $table->index('is_demo');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->boolean('is_demo')->default(false)->after('is_featured');
            $table->string('lifecycle_status', 32)->default('active')->after('is_demo');
            $table->index('is_demo');
            $table->index('lifecycle_status');
        });

        Schema::table('configuration_price_tiers', function (Blueprint $table) {
            $table->string('tier_type', 32)->default('fixed_unit')->after('min_quantity');
            $table->decimal('discount_percent', 5, 2)->nullable()->after('unit_price');
        });

        DB::table('products')->where('is_active', true)->update(['lifecycle_status' => 'active']);
        DB::table('products')->where('is_active', false)->update(['lifecycle_status' => 'draft']);
    }

    public function down(): void
    {
        Schema::table('configuration_price_tiers', function (Blueprint $table) {
            $table->dropColumn(['tier_type', 'discount_percent']);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['is_demo']);
            $table->dropIndex(['lifecycle_status']);
            $table->dropColumn(['is_demo', 'lifecycle_status']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['is_demo']);
            $table->dropColumn('is_demo');
        });
    }
};
