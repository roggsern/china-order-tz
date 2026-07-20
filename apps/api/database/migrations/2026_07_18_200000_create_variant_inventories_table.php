<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Standalone Inventory Engine — stock owned by product variants (per warehouse).
 * Distinct from legacy `inventory` table used by the commerce config engine.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('variant_inventories')) {
            return;
        }

        Schema::create('variant_inventories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('product_variant_id')
                ->constrained('product_variants')
                ->cascadeOnDelete();
            $table->string('warehouse_code', 32)->default('MAIN');
            $table->unsignedInteger('on_hand')->default(0);
            $table->unsignedInteger('reserved')->default(0);
            $table->unsignedInteger('reorder_level')->default(5);
            $table->unsignedInteger('safety_stock')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index('product_variant_id');
            $table->index('warehouse_code');
            $table->unique(['product_variant_id', 'warehouse_code'], 'variant_inventories_variant_warehouse_uq');
        });

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE variant_inventories ADD available INT GENERATED ALWAYS AS (on_hand - reserved) STORED');
        } elseif ($driver === 'pgsql') {
            DB::statement('ALTER TABLE variant_inventories ADD available integer GENERATED ALWAYS AS (on_hand - reserved) STORED');
        } else {
            // sqlite / others — maintain via model events; column is writable.
            Schema::table('variant_inventories', function (Blueprint $table) {
                $table->integer('available')->default(0)->after('reserved');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('variant_inventories');
    }
};
