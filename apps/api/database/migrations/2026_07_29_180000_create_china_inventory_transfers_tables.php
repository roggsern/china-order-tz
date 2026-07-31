<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ADMIN-11.3 — China → Tanzania inventory transfer pipeline (non-sellable until TZ receive).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('china_inventory_transfers')) {
            Schema::create('china_inventory_transfers', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('transfer_number', 40)->unique();
                $table->string('status', 40);
                $table->foreignUuid('created_by')->nullable()->constrained('admins')->nullOnDelete();
                $table->text('notes')->nullable();
                $table->timestamp('received_china_at')->nullable();
                $table->timestamp('quality_checked_at')->nullable();
                $table->timestamp('ready_for_export_at')->nullable();
                $table->timestamp('shipped_at')->nullable();
                $table->timestamp('in_transit_at')->nullable();
                $table->timestamp('arrived_tanzania_at')->nullable();
                $table->timestamp('received_tanzania_at')->nullable();
                $table->timestamp('cancelled_at')->nullable();
                $table->timestamps();

                $table->index('status');
            });
        }

        if (! Schema::hasTable('china_inventory_transfer_lines')) {
            Schema::create('china_inventory_transfer_lines', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('china_inventory_transfer_id')
                    ->constrained('china_inventory_transfers')
                    ->cascadeOnDelete();
                $table->foreignUuid('product_variant_id')
                    ->constrained('product_variants')
                    ->cascadeOnDelete();
                $table->unsignedInteger('quantity');
                $table->timestamps();

                $table->unique(
                    ['china_inventory_transfer_id', 'product_variant_id'],
                    'china_transfer_lines_transfer_variant_uq',
                );
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('china_inventory_transfer_lines');
        Schema::dropIfExists('china_inventory_transfers');
    }
};
