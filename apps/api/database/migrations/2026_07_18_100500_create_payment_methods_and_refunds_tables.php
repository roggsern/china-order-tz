<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MASTER_SPECIFICATION: Payments — payment_methods, refunds.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_methods', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedInteger('sort_order')->default(0)->index();
            $table->json('config')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('refunds', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('payment_id')->constrained('payments')->cascadeOnDelete();
            $table->foreignUuid('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3)->default('TZS');
            $table->string('status')->default('pending')->index();
            $table->string('reason')->nullable();
            $table->string('gateway_ref')->nullable()->index();
            $table->timestamp('refunded_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['payment_id', 'status']);
            $table->index(['order_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('refunds');
        Schema::dropIfExists('payment_methods');
    }
};
