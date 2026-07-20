<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MASTER_SPECIFICATION: Orders & Fulfillment —
 * order_status_history, order_tracking_events, shipments.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_status_history', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignUuid('changed_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->foreignUuid('changed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('previous_status')->nullable();
            $table->string('new_status');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['order_id', 'created_at']);
            $table->index(['new_status', 'created_at']);
        });

        Schema::create('order_tracking_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('order_id')->constrained('orders')->cascadeOnDelete();
            $table->string('status')->nullable()->index();
            $table->string('location')->nullable();
            $table->text('description')->nullable();
            $table->timestamp('occurred_at')->index();
            $table->timestamps();

            $table->index(['order_id', 'occurred_at']);
        });

        Schema::create('shipments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('order_id')->constrained('orders')->cascadeOnDelete();
            $table->string('carrier')->nullable();
            $table->string('tracking_number')->nullable()->index();
            $table->string('status')->default('pending')->index();
            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['order_id', 'status']);
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->foreignUuid('shipment_id')
                ->nullable()
                ->after('estimated_delivery_days')
                ->constrained('shipments')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('shipment_id');
        });

        Schema::dropIfExists('shipments');
        Schema::dropIfExists('order_tracking_events');
        Schema::dropIfExists('order_status_history');
    }
};
