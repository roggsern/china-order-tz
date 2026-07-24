<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_shipping_backfill_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('batch_id');
            $table->foreignUuid('product_id')->constrained('products')->cascadeOnDelete();
            $table->json('created_option_ids')->nullable();
            $table->json('previous_state')->nullable();
            $table->string('action', 32);
            $table->string('reason', 255)->nullable();
            $table->timestamp('executed_at')->nullable();
            $table->boolean('rolled_back')->default(false);
            $table->timestamp('rolled_back_at')->nullable();
            $table->timestamps();

            $table->index(['batch_id', 'action']);
            $table->index(['product_id', 'rolled_back']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_shipping_backfill_logs');
    }
};
