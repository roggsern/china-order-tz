<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Warehouse Operations Foundation — pick lists, packing, locations, stock transfers.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('warehouse_facilities')) {
            Schema::create('warehouse_facilities', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('code', 32)->unique();
                $table->string('name');
                $table->string('inventory_warehouse_code', 32)->nullable()->index();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('warehouse_zones')) {
            Schema::create('warehouse_zones', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('facility_id')->constrained('warehouse_facilities')->cascadeOnDelete();
                $table->string('code', 32);
                $table->string('name');
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->unique(['facility_id', 'code']);
            });
        }

        if (! Schema::hasTable('warehouse_bins')) {
            Schema::create('warehouse_bins', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('zone_id')->constrained('warehouse_zones')->cascadeOnDelete();
                $table->string('code', 32);
                $table->string('name');
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->unique(['zone_id', 'code']);
            });
        }

        if (! Schema::hasTable('product_variant_warehouse_bins')) {
            Schema::create('product_variant_warehouse_bins', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('product_variant_id')->constrained('product_variants')->cascadeOnDelete();
                $table->foreignUuid('warehouse_bin_id')->constrained('warehouse_bins')->cascadeOnDelete();
                $table->boolean('is_primary')->default(false);
                $table->timestamps();

                $table->unique(['product_variant_id', 'warehouse_bin_id'], 'pv_wh_bins_variant_bin_uq');
            });
        } elseif (! $this->indexExists('product_variant_warehouse_bins', 'pv_wh_bins_variant_bin_uq')) {
            Schema::table('product_variant_warehouse_bins', function (Blueprint $table) {
                $table->unique(['product_variant_id', 'warehouse_bin_id'], 'pv_wh_bins_variant_bin_uq');
            });
        }

        if (! Schema::hasTable('warehouse_pick_lists')) {
            Schema::create('warehouse_pick_lists', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('warehouse_job_id')->constrained('warehouse_jobs')->cascadeOnDelete();
                $table->foreignUuid('order_id')->constrained('orders')->cascadeOnDelete();
                $table->foreignUuid('picker_id')->nullable()->constrained('admins')->nullOnDelete();
                $table->string('status')->default('pending');
                $table->timestamp('started_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamps();

                $table->unique('warehouse_job_id');
                $table->index('status');
            });
        }

        if (! Schema::hasTable('warehouse_pick_list_lines')) {
            Schema::create('warehouse_pick_list_lines', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('pick_list_id')->constrained('warehouse_pick_lists')->cascadeOnDelete();
                $table->foreignUuid('order_item_id')->constrained('order_items')->cascadeOnDelete();
                $table->foreignUuid('product_variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
                $table->string('product_name');
                $table->string('sku')->nullable();
                $table->unsignedInteger('quantity');
                $table->unsignedInteger('picked_quantity')->default(0);
                $table->foreignUuid('warehouse_bin_id')->nullable()->constrained('warehouse_bins')->nullOnDelete();
                $table->string('status')->default('pending');
                $table->timestamps();

                $table->unique(['pick_list_id', 'order_item_id'], 'wh_pick_lines_list_item_uq');
            });
        }

        if (! Schema::hasTable('warehouse_packing_records')) {
            Schema::create('warehouse_packing_records', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('warehouse_job_id')->constrained('warehouse_jobs')->cascadeOnDelete();
                $table->foreignUuid('packer_id')->nullable()->constrained('admins')->nullOnDelete();
                $table->string('status')->default('pending');
                $table->string('package_status')->nullable();
                $table->text('notes')->nullable();
                $table->timestamp('started_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamps();

                $table->unique('warehouse_job_id');
                $table->index('status');
            });
        }

        if (! Schema::hasTable('warehouse_packing_lines')) {
            Schema::create('warehouse_packing_lines', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('packing_record_id')->constrained('warehouse_packing_records')->cascadeOnDelete();
                $table->foreignUuid('order_item_id')->constrained('order_items')->cascadeOnDelete();
                $table->unsignedInteger('quantity');
                $table->unsignedInteger('packed_quantity')->default(0);
                $table->timestamps();

                $table->unique(['packing_record_id', 'order_item_id'], 'wh_pack_lines_rec_item_uq');
            });
        }

        if (! Schema::hasTable('warehouse_stock_transfers')) {
            Schema::create('warehouse_stock_transfers', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('transfer_number')->unique();
                $table->foreignUuid('from_facility_id')->constrained('warehouse_facilities');
                $table->foreignUuid('to_facility_id')->constrained('warehouse_facilities');
                $table->string('status')->default('requested');
                $table->foreignUuid('requested_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
                $table->foreignUuid('approved_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
                $table->text('notes')->nullable();
                $table->timestamp('requested_at')->nullable();
                $table->timestamp('approved_at')->nullable();
                $table->timestamp('transferred_at')->nullable();
                $table->timestamp('cancelled_at')->nullable();
                $table->timestamps();

                $table->index('status');
            });
        }

        if (! Schema::hasTable('warehouse_stock_transfer_lines')) {
            Schema::create('warehouse_stock_transfer_lines', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('transfer_id')->constrained('warehouse_stock_transfers')->cascadeOnDelete();
                $table->foreignUuid('product_variant_id')->constrained('product_variants')->cascadeOnDelete();
                $table->unsignedInteger('quantity');
                $table->timestamps();

                $table->unique(['transfer_id', 'product_variant_id'], 'wh_xfer_lines_var_uq');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('warehouse_stock_transfer_lines');
        Schema::dropIfExists('warehouse_stock_transfers');
        Schema::dropIfExists('warehouse_packing_lines');
        Schema::dropIfExists('warehouse_packing_records');
        Schema::dropIfExists('warehouse_pick_list_lines');
        Schema::dropIfExists('warehouse_pick_lists');
        Schema::dropIfExists('product_variant_warehouse_bins');
        Schema::dropIfExists('warehouse_bins');
        Schema::dropIfExists('warehouse_zones');
        Schema::dropIfExists('warehouse_facilities');
    }

    private function indexExists(string $table, string $index): bool
    {
        $indexes = Schema::getConnection()->select(
            'SHOW INDEX FROM '.$table.' WHERE Key_name = ?',
            [$index]
        );

        return count($indexes) > 0;
    }
};
