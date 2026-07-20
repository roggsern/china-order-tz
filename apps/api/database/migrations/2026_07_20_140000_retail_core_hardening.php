<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TASK 040B — POS sale idempotency + high-frequency query indexes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pos_sale_idempotency_keys', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('idempotency_key', 128);
            $table->foreignUuid('admin_id')->constrained('admins')->cascadeOnDelete();
            $table->foreignUuid('pos_session_id')->constrained('pos_sessions')->cascadeOnDelete();
            $table->foreignUuid('order_id')->constrained('orders')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['admin_id', 'idempotency_key']);
            $table->index('order_id');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->index('pos_session_id');
            $table->index(['store_id', 'created_at']);
        });

        Schema::table('return_requests', function (Blueprint $table) {
            $table->index(['store_id', 'completed_at']);
            $table->index('processed_by');
        });

        Schema::table('refund_transactions', function (Blueprint $table) {
            $table->index(['order_id', 'status']);
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->index(['status', 'paid_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_sale_idempotency_keys');

        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['pos_session_id']);
            $table->dropIndex(['store_id', 'created_at']);
        });

        Schema::table('return_requests', function (Blueprint $table) {
            $table->dropIndex(['store_id', 'completed_at']);
            $table->dropIndex(['processed_by']);
        });

        Schema::table('refund_transactions', function (Blueprint $table) {
            $table->dropIndex(['order_id', 'status']);
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex(['status', 'paid_at']);
        });
    }
};
