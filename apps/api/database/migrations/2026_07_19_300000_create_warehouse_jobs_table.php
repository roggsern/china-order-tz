<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Warehouse Operations Engine — internal pick/pack workflow after payment.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('warehouse_jobs')) {
            return;
        }

        Schema::create('warehouse_jobs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignUuid('fulfillment_id')->constrained('fulfillments')->cascadeOnDelete();
            $table->string('job_number')->unique();
            $table->string('status')->default('pending');
            $table->foreignUuid('picker_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->foreignUuid('packer_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamp('picked_at')->nullable();
            $table->timestamp('packed_at')->nullable();
            $table->timestamp('ready_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique('order_id');
            $table->unique('fulfillment_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('warehouse_jobs');
    }
};
