<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_store_backfill_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('batch_id');
            $table->foreignUuid('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignUuid('previous_store_id')->nullable()->constrained('stores')->nullOnDelete();
            $table->foreignUuid('assigned_store_id')->nullable()->constrained('stores')->nullOnDelete();
            $table->string('action', 32);
            $table->string('reason', 255)->nullable();
            $table->string('lifecycle_status', 32)->nullable();
            $table->boolean('rolled_back')->default(false);
            $table->timestamp('rolled_back_at')->nullable();
            $table->timestamps();

            $table->index(['batch_id', 'action']);
            $table->index(['product_id', 'rolled_back']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_store_backfill_logs');
    }
};
