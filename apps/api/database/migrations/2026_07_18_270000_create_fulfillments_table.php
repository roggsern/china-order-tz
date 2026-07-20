<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fulfillment Engine — one fulfillment record per paid order.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('fulfillments')) {
            return;
        }

        Schema::create('fulfillments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('order_id')->constrained('orders')->cascadeOnDelete();
            $table->string('strategy'); // local | china
            $table->string('status')->default('pending');
            $table->foreignUuid('assigned_to')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique('order_id');
            $table->index('strategy');
            $table->index('status');
            $table->index('assigned_to');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fulfillments');
    }
};
