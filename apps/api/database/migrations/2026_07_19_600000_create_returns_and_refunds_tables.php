<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Returns & Refunds Engine — controlled return workflow (no automatic refunds).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('return_requests')) {
            Schema::create('return_requests', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('order_id')->constrained('orders')->cascadeOnDelete();
                $table->foreignUuid('customer_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('status')->default('requested')->index();
                $table->string('reason');
                $table->text('description')->nullable();
                $table->text('customer_notes')->nullable();
                $table->text('admin_notes')->nullable();
                $table->foreignUuid('approved_by')->nullable()->constrained('admins')->nullOnDelete();
                $table->timestamp('approved_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamps();

                $table->index(['customer_id', 'status']);
                $table->index(['order_id', 'status']);
            });
        }

        if (! Schema::hasTable('return_items')) {
            Schema::create('return_items', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('return_request_id')->constrained('return_requests')->cascadeOnDelete();
                $table->foreignUuid('order_item_id')->constrained('order_items')->cascadeOnDelete();
                $table->unsignedInteger('quantity');
                $table->string('reason')->nullable();
                $table->string('condition')->nullable();
                $table->string('resolution')->nullable();
                $table->decimal('refund_amount', 12, 2)->nullable();
                $table->boolean('replacement_requested')->default(false);
                $table->timestamps();

                $table->index(['return_request_id', 'order_item_id']);
            });
        }

        if (! Schema::hasTable('refund_transactions')) {
            Schema::create('refund_transactions', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('return_request_id')->constrained('return_requests')->cascadeOnDelete();
                $table->foreignUuid('order_id')->constrained('orders')->cascadeOnDelete();
                $table->decimal('amount', 12, 2);
                $table->string('currency', 3)->default('TZS');
                $table->string('status')->default('pending')->index();
                $table->string('method')->default('manual');
                $table->string('reference')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->index(['return_request_id', 'status']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('refund_transactions');
        Schema::dropIfExists('return_items');
        Schema::dropIfExists('return_requests');
    }
};
