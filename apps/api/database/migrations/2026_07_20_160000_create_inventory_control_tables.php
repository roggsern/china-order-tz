<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TASK 042 — Inventory Control & Stock Operations.
 * Extends VariantInventory; does not replace it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('variant_inventories', function (Blueprint $table) {
            $table->unsignedInteger('damaged')->default(0)->after('reserved');
            $table->unsignedInteger('inspection')->default(0)->after('damaged');
        });

        Schema::create('inventory_stock_movements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('variant_inventory_id')->nullable()->constrained('variant_inventories')->nullOnDelete();
            $table->foreignUuid('product_variant_id')->constrained('product_variants')->cascadeOnDelete();
            $table->foreignUuid('inventory_location_id')->nullable()->constrained('inventory_locations')->nullOnDelete();
            $table->foreignUuid('store_id')->nullable()->constrained('stores')->nullOnDelete();
            $table->string('movement_type', 32); // receive|sale|return|adjustment|damage|correction
            $table->integer('quantity_before');
            $table->integer('quantity_change');
            $table->integer('quantity_after');
            $table->unsignedInteger('damaged_after')->default(0);
            $table->unsignedInteger('inspection_after')->default(0);
            $table->string('reason')->nullable();
            $table->string('reference_type')->nullable();
            $table->uuid('reference_id')->nullable();
            $table->string('actor_type', 32)->nullable(); // admin|system
            $table->uuid('actor_id')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['store_id', 'created_at']);
            $table->index(['product_variant_id', 'created_at']);
            $table->index(['movement_type', 'created_at']);
            $table->index(['reference_type', 'reference_id']);
        });

        Schema::create('inventory_count_sessions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('count_number', 64)->unique();
            $table->foreignUuid('store_id')->constrained('stores')->cascadeOnDelete();
            $table->foreignUuid('inventory_location_id')->nullable()->constrained('inventory_locations')->nullOnDelete();
            $table->string('scope', 32)->default('full'); // full|category|selected
            $table->foreignUuid('category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->string('status', 32)->default('draft'); // draft|counting|pending_approval|approved|cancelled
            $table->text('notes')->nullable();
            $table->foreignUuid('created_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->foreignUuid('approved_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->index(['store_id', 'status']);
        });

        Schema::create('inventory_count_lines', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('inventory_count_session_id')->constrained('inventory_count_sessions')->cascadeOnDelete();
            $table->foreignUuid('product_variant_id')->constrained('product_variants')->cascadeOnDelete();
            $table->foreignUuid('variant_inventory_id')->nullable()->constrained('variant_inventories')->nullOnDelete();
            $table->unsignedInteger('system_quantity')->default(0);
            $table->unsignedInteger('counted_quantity')->nullable();
            $table->integer('difference')->nullable();
            $table->string('reason')->nullable();
            $table->boolean('is_adjusted')->default(false);
            $table->timestamps();

            $table->unique(['inventory_count_session_id', 'product_variant_id'], 'inv_count_line_unique');
        });

        Schema::table('receiving_records', function (Blueprint $table) {
            $table->foreignUuid('store_id')->nullable()->after('purchase_order_id')->constrained('stores')->nullOnDelete();
            $table->foreignUuid('inventory_location_id')->nullable()->after('store_id')->constrained('inventory_locations')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('receiving_records', function (Blueprint $table) {
            $table->dropConstrainedForeignId('inventory_location_id');
            $table->dropConstrainedForeignId('store_id');
        });

        Schema::dropIfExists('inventory_count_lines');
        Schema::dropIfExists('inventory_count_sessions');
        Schema::dropIfExists('inventory_stock_movements');

        Schema::table('variant_inventories', function (Blueprint $table) {
            $table->dropColumn(['damaged', 'inspection']);
        });
    }
};
